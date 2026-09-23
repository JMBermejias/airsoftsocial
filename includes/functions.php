<?php
/**
 * Funciones y helpers de Social Airsoft
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

/* Conexión PDO única (singleton) */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $port = defined('DB_PORT') && DB_PORT ? DB_PORT : '3306';
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Error de conexión con la base de datos. Comprueba config.php o ejecuta install.php');
        }
    }
    return $pdo;
}

function t(string $table): string {
    return DB_PREFIX . $table;
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/* ---------- Sesión / usuario ---------- */

function login_user(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
}

function logout_user(): void {
    unset($_SESSION['user_id']);
    session_destroy();
}

function is_logged(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    static $user = null;
    if (!is_logged()) return null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user ?: null;
}

function require_login(): void {
    if (!is_logged()) redirect('index.php');
}

function require_admin(): void {
    require_login();
    $u = current_user();
    if (!$u || !$u['is_admin']) redirect('feed.php');
}

function is_admin(): bool {
    $u = current_user();
    return $u && (int)$u['is_admin'] === 1;
}

/* ---------- CSRF ---------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
        http_response_code(400);
        die('Token de seguridad inválido. Vuelve atrás e inténtalo de nuevo.');
    }
}

/* ---------- Subida de archivos ---------- */

const MIME_IMG = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function upload_file(array $file, string $folder, string $kind = 'image'): array {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => ''];
    }
    $err = $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'El archivo supera el límite que permite tu servidor (upload_max_filesize / post_max_size).'];
        }
        return ['ok' => false, 'error' => 'Error al subir el archivo (código ' . $err . ').'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);

    $ok = ($kind === 'image' && in_array($mime, MIME_IMG, true))
        || ($kind === 'pdf' && $mime === 'application/pdf')
        || ($kind === 'any');
    if (!$ok) {
        return ['ok' => false, 'error' => 'Tipo de archivo no permitido.'];
    }

    $ext = ($mime === 'application/pdf') ? 'pdf' : str_replace('image/', '', $mime);
    if ($ext === 'svg+xml') $ext = 'svg';
    $name = $kind === 'image' ? bin2hex(random_bytes(8)) . '.' . $ext : bin2hex(random_bytes(8)) . '.pdf';

    $base = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($base)) mkdir($base, 0775, true);

    if (!move_uploaded_file($file['tmp_name'], $base . '/' . $name)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo. Comprueba los permisos de la carpeta uploads/.'];
    }
    return ['ok' => true, 'path' => 'uploads/' . $folder . '/' . $name];
}

/* ---------- Utilidades varias ---------- */

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'ahora mismo';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' d';
    return date('d/m/Y', strtotime($datetime));
}

function time_till(string $datetime): string {
    $diff = strtotime($datetime) - time();
    if ($diff < 60) return 'menos de 1 min';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h ' . floor(($diff % 3600) / 60) . ' min';
    return floor($diff / 86400) . ' d';
}

function avatar_src(?string $avatar): string {
    return $avatar ? e($avatar) : 'assets/img/default-avatar.svg';
}

function notify(int $recipient, ?int $actor, string $type, string $message, string $link): void {
    $stmt = db()->prepare('INSERT INTO ' . t('notifications') . ' (user_id, actor_id, type, message, link) VALUES (?,?,?,?,?)');
    $stmt->execute([$recipient, $actor, $type, $message, $link]);
}

function unread_notifs(): int {
    if (!is_logged()) return 0;
    $stmt = db()->prepare('SELECT COUNT(*) c FROM ' . t('notifications') . ' WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$_SESSION['user_id']]);
    return (int)$stmt->fetch()['c'];
}

function unread_requests(): int {
    if (!is_logged()) return 0;
    $stmt = db()->prepare(
        'SELECT COUNT(*) c FROM ' . t('friendships') . '
         WHERE friend_id = ? AND status = "pending" AND requester_id <> ?'
    );
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    return (int)$stmt->fetch()['c'];
}

/* ---------- Amistades ---------- */

function friendship_status(int $me, int $other): string {
    if ($me === $other) return 'self';
    $stmt = db()->prepare('SELECT * FROM ' . t('friendships') . ' WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1');
    $stmt->execute([$me, $other, $other, $me]);
    $row = $stmt->fetch();
    if (!$row) return 'none';
    if ($row['status'] === 'pending') {
        return $row['requester_id'] == $me ? 'pending_sent' : 'pending_received';
    }
    return 'friends';
}

function friend_ids(int $me): array {
    $stmt = db()->prepare('SELECT user_id, friend_id FROM ' . t('friendships') . ' WHERE status = "accepted" AND (user_id = ? OR friend_id = ?)');
    $stmt->execute([$me, $me]);
    $ids = [];
    foreach ($stmt->fetchAll() as $r) {
        $ids[] = (int)($r['user_id'] == $me ? $r['friend_id'] : $r['user_id']);
    }
    return $ids;
}

function send_friend_request(int $from, int $to): int {
    if ($from === $to) return 0;
    $e = db();
    $stmt = $e->prepare('INSERT INTO ' . t('friendships') . ' (user_id, friend_id, requester_id) VALUES (?,?,?)');
    $stmt->execute([$from, $to, $from]);
    notify($to, $from, 'friend', 'Te ha enviado una solicitud de amistad', 'friends.php');
    return (int)$e->lastInsertId();
}

function accept_friend_request(int $me, int $from): void {
    $e = db();
    $stmt = $e->prepare('UPDATE ' . t('friendships') . ' SET status = "accepted" WHERE user_id = ? AND friend_id = ? AND status = "pending" AND requester_id = ?');
    $stmt->execute([$from, $me, $from]);
    notify($from, $me, 'friend', 'Ha aceptado tu solicitud de amistad', 'friends.php');
}

function reject_friend_request(int $me, int $from): void {
    $stmt = db()->prepare('DELETE FROM ' . t('friendships') . ' WHERE user_id = ? AND friend_id = ? AND status = "pending"');
    $stmt->execute([$from, $me]);
}

function remove_friendship(int $me, int $other): void {
    $stmt = db()->prepare('DELETE FROM ' . t('friendships') . ' WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))');
    $stmt->execute([$me, $other, $other, $me]);
}

/* ---------- Historias ----------
 * Las historias son PERMANENTES por defecto (expires_at NULL).
 * Solo se eliminan solas cuando el usuario las crea como "historia de 24 h"
 * y esas 24 horas han pasado. Propias y de amigos, visibles solo para amigos.
 */
function visible_stories(int $me): array {
    $ids = friend_ids($me);
    $ids[] = $me;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT s.*, u.username, u.avatar FROM ' . t('stories') . ' s JOIN ' . t('users') . ' u ON u.id = s.user_id
         WHERE (s.expires_at IS NULL OR s.expires_at > NOW()) AND s.user_id IN (' . $in . ')
         ORDER BY (s.expires_at IS NULL) DESC, s.created_at DESC LIMIT 50'
    );
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

/* ---------- Etiquetas (tags) ---------- */

function parse_tags(string $tags): array {
    return array_values(array_filter(array_map('trim', explode(',', $tags))));
}

function render_tags(string $tags): string {
    $html = '';
    foreach (parse_tags($tags) as $tg) {
        $html .= '<span class="tag">#' . e($tg) . '</span>';
    }
    return $html;
}