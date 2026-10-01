<?php
/**
 * Funciones y helpers de Airsoft Social
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$__config = __DIR__ . '/../config.php';
if (is_file($__config)) {
    require_once $__config;
} else {
    /* Sin config.php: solo ocurre durante la instalación (install.php lo crea). */
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', '');
    define('DB_USER', '');
    define('DB_PASS', '');
    define('DB_PREFIX', '');
    define('APP_NAME', 'Airsoft Social');
    date_default_timezone_set('Europe/Madrid');
}

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
            /* Mensaje legible: el error de MySQL ayuda a saber si es un host
             * equivocado, una base de datos que no existe o credenciales. */
            $why = $e->getMessage();
            $hint = 'Revisa DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS en config.php. '
                  . 'Si la base de datos no existe todavía, abre install.php.';
            if (strpos($why, '1045') !== false) {
                $hint = 'El usuario o la contraseña no son correctos (error 1045). Revisa DB_USER y DB_PASS en config.php.';
            } elseif (strpos($why, '1044') !== false) {
                $hint = 'Ese usuario de MySQL no tiene permiso sobre la base de datos "' . DB_NAME
                      . '" o esta base de datos no existe (error 1044). Revisa DB_NAME en config.php y pide a tu hosting que te cree la base con ese nombre.';
            } elseif (strpos($why, '1049') !== false || strpos($why, '2002') !== false || strpos($why, '2003') !== false) {
                $hint = 'No se encuentra el servidor o la base de datos (revisa DB_HOST, DB_PORT y DB_NAME en config.php).';
            } elseif (strpos($why, '2005') !== false) {
                $hint = 'El servidor MySQL rechazó la conexión por clave (revisa DB_HOST y DB_PORT en config.php).';
            }
            if (PHP_SAPI !== 'cli') {
                header('Content-Type: text/html; charset=utf-8', true, 500);
            }
            die('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
                . '<title>Error de conexión · ' . e(defined('APP_NAME') ? APP_NAME : 'Airsoft Social') . '</title></head>'
                . '<body style="font-family:system-ui,sans-serif;max-width:620px;margin:12vh auto;padding:0 20px;'
                . 'background:#14170f;color:#e8eae2">'
                . '<h1 style="color:#c8d64b;font-size:22px;margin:0 0 12px">Error de conexión con la base de datos</h1>'
                . '<p style="line-height:1.6">' . e($hint) . '</p>'
                . '<p style="color:#8a9080;font-size:13px;line-height:1.5">Detalle técnico: <code>' . e($why) . '</code></p>'
                . '</body></html>');
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

/* Ruta base de la app en la URL ('' si está en la raíz del hosting, o '/subcarpeta').
 * Necesario para que los redirect() funcionen estando dentro de actions/. */
function app_base(): string {
    static $base = false;
    if ($base === false) {
        $root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $dir = str_replace('\\', '/', dirname(__DIR__));
        $rel = ($root !== '' && strpos($dir, $root) === 0) ? substr($dir, strlen($root)) : '';
        $base = rtrim('/' . trim($rel, '/'), '/');
    }
    return $base;
}

function redirect(string $path): void {
    if (preg_match('#^https?://#i', $path) || substr($path, 0, 1) === '/') {
        header('Location: ' . $path);
    } else {
        header('Location: ' . app_base() . '/' . $path);
    }
    exit;
}

/* ---------- Sesión / usuario ---------- */

/* Busca una cuenta por nombre de usuario o por correo electrónico.
 * Se puede entrar con las dos cosas. Tolera espacios sobrantes, mayúsculas
 * (el cotejo de MySQL ya ignora mayúsculas) y el "@" pegado al correo.
 * Devuelve null si no encuentra nada. */
function find_user_by_login(string $id): ?array {
    $id = trim($id);
    if ($id === '') return null;

    $stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$id, $id]);
    $u = $stmt->fetch();
    if ($u) return $u;

    /* AL REGISTRARSE se borran del nombre los puntos, guiones y espacios: si
     * escribiste «jose.perez» o «jose-perez» se quedó guardado «joseperez».
     * Con la búsqueda de arriba no lo encuentra, pero la persona sí recuerda
     * el nombre que escribió, así que se busca también en esa forma "limpia".
     * Es la causa más habitual de «me dice que no hay ninguna cuenta» cuando la
     * cuenta sí existe. */
    $plain = preg_replace('/[^A-Za-z0-9_]/', '', $id);
    if ($plain !== '' && $plain !== $id) {
        $s2 = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE username = ? LIMIT 1');
        $s2->execute([$plain]);
        $u = $s2->fetch();
        if ($u) return $u;
    }

    /* Alguien que escribe «nombre@» sin el dominio, o «@dominio.com»
     * se queda sin nombre: se prueba con lo que haya antes del arroba. */
    if (strpos($id, '@') !== false) {
        $part = trim(strstr($id, '@', true) ?: '', '.');
        if (mb_strlen($part) >= 3) {
            $stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE username = ? LIMIT 1');
            $stmt->execute([$part]);
            $u = $stmt->fetch();
            if ($u) return $u;
        }
    }
    return null;
}

/* Convierte un correo en un nombre de usuario válido y único:
 * "juan.perez@correo.com" -> "juanperez" (si ya está cogido, "juanperez2",
 * "juanperez3"...). Nunca devuelve una cadena vacía ni un nombre repetido. */
function username_from_email(string $email, PDO $pdo): string {
    $email = trim($email);
    /* Lo de antes del arroba; si el correo no tiene arroba, se usa entero. */
    $local = ($at = strpos($email, '@')) !== false ? substr($email, 0, $at) : $email;
    $base = strtolower((string)preg_replace('/[^A-Za-z0-9_]/', '', $local));
    if (mb_strlen($base) < 3) $base = 'usuario' . substr(sha1($email), 0, 5);
    $base = mb_substr($base, 0, 40);

    $candidate = $base;
    for ($n = 2; $n < 1000; $n++) {
        $chk = $pdo->prepare('SELECT id FROM ' . t('users') . ' WHERE username = ? LIMIT 1');
        $chk->execute([$candidate]);
        if (!$chk->fetch()) return $candidate;
        $candidate = mb_substr($base, 0, 40 - strlen((string)$n)) . $n;
    }
    return $base . substr(sha1($email), 0, 5);
}

/* Cuentas que se quedaron sin nombre de usuario (base de datos antigua o
 * altas manuales): con estas cuentas solo se puede entrar por correo. */
function users_without_username(): array {
    try {
        return db()->query('SELECT id, email FROM ' . t('users')
            . " WHERE username IS NULL OR username = '' ORDER BY id")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/* Les asigna un nombre de usuario a todas las cuentas que no lo tienen.
 * Devuelve cuántas se han arreglado. */
function repair_usernames(): int {
    $pdo = db();
    $n = 0;
    foreach (users_without_username() as $row) {
        $upd = $pdo->prepare('UPDATE ' . t('users') . ' SET username = ? WHERE id = ? AND (username IS NULL OR username = \'\')');
        $upd->execute([username_from_email((string)$row['email'], $pdo), (int)$row['id']]);
        $n += $upd->rowCount();
    }
    return $n;
}

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
const MIME_VIDEO = ['video/mp4', 'video/webm', 'video/quicktime'];
const VIDEO_EXT = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
/* Tope de longitud de una URL remota que aceptamos descargar o medir. Antes
 * cada sitio usaba un número distinto (600, 1000) y se acababa rechazando la
 * misma imagen en un sitio y aceptándola en otro. */
const REMOTE_URL_MAX = 600;

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
    if (PHP_VERSION_ID < 80000) finfo_close($finfo);

    $ok = ($kind === 'image' && in_array($mime, MIME_IMG, true))
        || ($kind === 'pdf' && $mime === 'application/pdf')
        || ($kind === 'video' && in_array($mime, MIME_VIDEO, true))
        || ($kind === 'any' && $mime !== '');
    if (!$ok) {
        return ['ok' => false, 'error' => 'Tipo de archivo no permitido.'];
    }

    $ext = ($mime === 'application/pdf')
        ? 'pdf'
        : (($kind === 'video') ? (VIDEO_EXT[$mime] ?? 'bin') : str_replace('image/', '', $mime));
    if ($ext === 'svg+xml') $ext = 'svg';
    $name = bin2hex(random_bytes(8)) . '.' . $ext;

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

/* ---------- Rangos militares por antigüedad ----------
 * Los usuarios suben de rango según los días desde su alta.
 * El administrador siempre es General (el de mayor grado).
 * Definición configurable con la constante MILITARY_RANKS (días => rango).
 */
function military_rank(array $user): string {
    if ((int)($user['is_admin'] ?? 0) === 1) return 'General';
    $ladder = [
        0    => 'Soldado',
        30   => 'Soldado de 1ª',
        90   => 'Cabo',
        180  => 'Sargento',
        365  => 'Teniente',
        730  => 'Capitán',
        1095 => 'Comandante',
        1460 => 'Coronel',
        1825 => 'Teniente General',
    ];
    if (defined('MILITARY_RANKS') && is_array(MILITARY_RANKS)) {
        $ladder = MILITARY_RANKS;
        ksort($ladder);
    }
    $days = (int)floor((time() - strtotime($user['created_at'] ?? 'now')) / 86400);
    if ($days < 0) $days = 0;
    $rank = 'Soldado';
    foreach ($ladder as $d => $r) {
        if ($days >= (int)$d) $rank = (string)$r;
    }
    return $rank;
}

function rank_badge(array $user): string {
    $rank = military_rank($user);
    $is_admin = (int)($user['is_admin'] ?? 0) === 1;
    $class = $is_admin ? 'rank-badge general' : 'rank-badge';
    return '<span class="' . $class . '" title="Rango según antigüedad">' . e($rank) . '</span>';
}

function days_registered(array $user): int {
    return (int)floor((time() - strtotime($user['created_at'] ?? 'now')) / 86400);
}

function admin_count(): int {
    static $n = null;
    if ($n === null) {
        $n = (int)db()->query('SELECT COUNT(*) FROM ' . t('users') . ' WHERE is_admin = 1')->fetchColumn();
    }
    return $n;
}

/* ---------- Tienda (afiliación) ---------- */

/* Métodos de pago activos configurados por el administrador. */
function store_payment_methods(): array {
    $stmt = db()->query('SELECT * FROM ' . t('payment_methods') . ' WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

/* URL de compra válida del producto (solo http/https). */
function product_buy_url(array $p): ?string {
    $u = trim((string)($p['url'] ?? ''));
    if ($u === '' || !preg_match('#^https?://#i', $u)) return null;
    return $u;
}

/* Tope de tamaño legible para los mensajes de error: 4194304 -> "4 MB".
 * Con round() a secas, un tope por debajo de medio megabyte salía como "0 MB",
 * que no dice nada. */
function human_bytes(int $n): string {
    if ($n >= 1048576) {
        $mb = $n / 1048576;
        return (abs($mb - round($mb)) < 0.05 ? (int)round($mb) : round($mb, 1)) . ' MB';
    }
    if ($n >= 1024) return max(1, (int)round($n / 1024)) . ' KB';
    return $n . ' B';
}

/* ---------- Peticiones HTTP salientes ----------
 * En hosting compartido es muy habitual que allow_url_fopen esté DESACTIVADO
 * (entonces file_get_contents con una URL no funciona) o que falte la extensión
 * cURL. Este helper intenta cURL y, si no existe, usa los flujos de PHP.
 * Nunca lanza errores: siempre devuelve el estado, el cuerpo y el motivo del
 * fallo, para poder explicárselo al administrador con palabras claras.
 *
 * Opciones: method, headers[], timeout, follow, user_agent, to_file, max_bytes.
 * Devuelve: code, body, headers[] (minúsculas), error, warning, via. */
function http_request(string $url, array $opt = []): array {
    $out = ['code' => 0, 'body' => '', 'headers' => [], 'error' => '', 'warning' => '', 'via' => ''];
    $method  = (string)($opt['method'] ?? 'GET');
    $timeout = max(3, (int)($opt['timeout'] ?? 20));
    $follow  = !array_key_exists('follow', $opt) || (bool)$opt['follow'];
    $ua      = (string)($opt['user_agent'] ?? 'AirsoftSocial');
    $toFile  = (string)($opt['to_file'] ?? '');
    $max     = (int)($opt['max_bytes'] ?? 0);
    $headers = array_values((array)($opt['headers'] ?? []));

    if (!preg_match('#^https?://#i', $url)) {
        $out['error'] = 'URL no válida: ' . $url;
        return $out;
    }

    /* 1) cURL: funciona aunque allow_url_fopen esté desactivado. */
    if (function_exists('curl_init')) {
        $out['via'] = 'curl';
        $aborted = false;
        $attempt = function (bool $verify) use ($url, $method, $timeout, $follow, $ua, $toFile, $headers, $max, &$aborted) {
            $aborted = false;
            $hdr = [];
            $fh  = null;
            if ($toFile !== '' && ($fh = @fopen($toFile, 'wb')) === false) {
                return ['res' => false, 'err' => 'No se pudo escribir en ' . $toFile, 'code' => 0, 'hdr' => [], 'size' => 0];
            }
            $ch = curl_init($url);
            $set = [
                CURLOPT_RETURNTRANSFER => $fh === null,
                CURLOPT_FOLLOWLOCATION => $follow,
                CURLOPT_MAXREDIRS       => 5,
                CURLOPT_CONNECTTIMEOUT  => min(10, $timeout),
                CURLOPT_TIMEOUT         => $timeout,
                CURLOPT_SSL_VERIFYPEER  => $verify,
                CURLOPT_SSL_VERIFYHOST  => $verify ? 2 : 0,
                CURLOPT_USERAGENT       => $ua,
                CURLOPT_HTTPHEADER      => $headers,
                CURLOPT_HEADERFUNCTION  => function ($c, $line) use (&$hdr) {
                    $p = strpos($line, ':');
                    if ($p !== false) $hdr[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
                    return strlen($line);
                },
            ];
            /* El tope de tamaño tiene que cortar la descarga EN MARCHA. Con
             * CURLOPT_FILE el cuerpo entero aterriza en disco, así que medir
             * después solo sirve para borrar: un fichero enorme llegaría a
             * llenar el disco temporal del hosting. MAXFILESIZE aborta si el
             * servidor manda Content-Length, y la función de progreso cubre
             * el caso de que no lo mande (transferencias por chunk). */
            if ($max > 0) {
                $set[CURLOPT_MAXFILESIZE]      = $max;
                $set[CURLOPT_NOPROGRESS]       = false;
                $set[CURLOPT_PROGRESSFUNCTION] = function ($c, $dlSize, $dlNow, $ulSize, $ulNow) use ($max, &$aborted) {
                    if ($dlNow > $max) { $aborted = true; return 1; } /* != 0 aborta */
                    return 0;
                };
            }
            curl_setopt_array($ch, $set);
            if ($fh !== null) curl_setopt($ch, CURLOPT_FILE, $fh);
            $res  = curl_exec($ch);
            $err  = (string)curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (PHP_VERSION_ID < 80000) curl_close($ch);
            if ($fh !== null) fclose($fh);
            return ['res' => $res, 'err' => $err, 'code' => $code, 'hdr' => $hdr, 'size' => strlen((string)$res)];
        };

        $r = $attempt(true);
        /* CA antigua en el hosting: si el certificado no valida, se reintenta
         * sin verificación y se avisa en la web. */
        if ($r['err'] !== '' && !$aborted && preg_match('/certificate|ssl|ca cert|issuer/i', $r['err'])) {
            $r2 = $attempt(false);
            if ($r2['err'] === '') {
                $r = $r2;
                $out['warning'] = 'Tu hosting no tiene los certificados de una CA actual: se aceptó la conexión sin verificarlos.';
            }
        }

        $out['code']    = (int)$r['code'];
        $out['headers'] = $r['hdr'];
        $out['body']    = is_string($r['res']) ? $r['res'] : '';
        /* Si la descarga se paró a mitad porque superaba el tope, el motivo
         * real es el tamaño, no el error interno de cURL. */
        if ($aborted) {
            @unlink($toFile);
            $out['error'] = 'El archivo descargado supera el tamaño máximo permitido ('
                . human_bytes($max) . ').';
            return $out;
        }
        if ($r['err'] !== '') {
            $out['error'] = 'cURL: ' . $r['err'];
            return $out;
        }
        if ($toFile !== '') {
            $size = is_file($toFile) ? (int)filesize($toFile) : 0;
            if ($max > 0 && $size > $max) {
                @unlink($toFile);
                $out['error'] = 'El archivo descargado supera el tamaño máximo permitido ('
                    . human_bytes($max) . ').';
                return $out;
            }
            if ($size === 0) {
                @unlink($toFile);
                $out['error'] = 'El servidor remoto devolvió el archivo vacío (código HTTP ' . $out['code'] . ').';
                return $out;
            }
        }
        if ($out['code'] >= 400) {
            $out['error'] = 'El servidor remoto respondió con el código HTTP ' . $out['code'] . '.';
        }
        return $out;
    }

    /* 2) Flujos de PHP (solo si allow_url_fopen está activado). */
    $out['via'] = 'streams';
    if (!filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $out['error'] = 'Este PHP no tiene la extensión cURL y tiene allow_url_fopen desactivado, así que la app no puede salir a Internet. Pide a tu hosting que active cURL.';
        return $out;
    }
    $raw = "User-Agent: $ua\r\nAccept-Encoding: identity\r\n";
    foreach ($headers as $h) $raw .= $h . "\r\n";
    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'timeout'       => $timeout,
        'header'        => $raw,
        'ignore_errors' => true,
        'follow_location' => $follow ? 1 : 0,
        'max_redirects' => 5,
    ]]);
    /* Se lee con fopen (no con file_get_contents) para poder sacar las
     * cabeceras de la respuesta sin usar la variable obsoleta
     * $http_response_header y para volcar el paquete directamente a disco. */
    $fp = @fopen($url, 'rb', false, $ctx);
    if ($fp === false) {
        $out['error'] = 'No se pudo abrir ' . $url . ' (revisa la salida a Internet del hosting y su certificado SSL).';
        return $out;
    }
    $meta = @stream_get_meta_data($fp);
    $rh   = isset($meta['wrapper_data']) && is_array($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
    $code = 0;
    $h    = [];
    foreach ($rh as $line) {
        if (!is_string($line)) continue;
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) { $code = (int)$m[1]; $h = []; continue; }
        $p = strpos($line, ':');
        if ($p !== false) $h[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
    }
    $out['code']    = $code;
    $out['headers'] = $h;

    $fh   = $toFile !== '' ? @fopen($toFile, 'wb') : null;
    $body = '';
    $size = 0;
    while (!feof($fp)) {
        $chunk = fread($fp, 65536);
        if ($chunk === false || $chunk === '') break;
        $size += strlen($chunk);
        if ($max > 0 && $size > $max) {
            fclose($fp);
            if ($fh !== null) fclose($fh);
            @unlink($toFile);
            $out['error'] = 'El archivo descargado supera el tamaño máximo permitido ('
                . human_bytes($max) . ').';
            return $out;
        }
        if ($fh !== null) fwrite($fh, $chunk);
        else $body .= $chunk;
    }
    fclose($fp);
    if ($fh !== null) {
        fclose($fh);
        if (!is_file($toFile) || filesize($toFile) === 0) {
            @unlink($toFile);
            $out['error'] = 'El servidor remoto devolvió el archivo vacío (código HTTP ' . $out['code'] . ').';
            return $out;
        }
    } else {
        $out['body'] = $body;
    }
    if ($out['code'] >= 400) {
        @unlink($toFile);
        $out['error'] = 'El servidor remoto respondió con el código HTTP ' . $out['code'] . '.';
    }
    return $out;
}

/* Abre una URL imitando un navegador real (UA, Accept-Language, Referer).
 * Devuelve [código HTTP, contenido HTML] o [0/'']. */
function http_get_like_browser(string $url): array {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    $r = http_request($url, [
        'user_agent' => $ua,
        'timeout'    => 25,
        'headers'    => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Referer: https://www.amazon.es/',
        ],
    ]);
    return [$r['code'], $r['body']];
}

/* Extrae título, precio, imagen y descripción reales de un enlace de tienda
 * (afiliado), leyendo las etiquetas OpenGraph o los selectores de Amazon.
 * Devuelve ['name','price','image','description'] y, si no se puede leer, 'error'. */
function fetch_product_meta(string $url): array {
    $out = ['name' => '', 'price' => '', 'image' => '', 'description' => ''];
    [$code, $html] = http_get_like_browser($url);
    if ($code !== 200 || $html === '' || stripos($html, 'captcha') !== false) {
        $out['error'] = 'La tienda bloqueó la lectura automática del enlace (verificación anti-bots). Copia el nombre, la imagen y el precio manualmente.';
        return $out;
    }

    if (preg_match('~<meta[^>]+property="og:title"[^>]+content="([^"]+)"~i', $html, $m)) {
        $out['name'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    } elseif (preg_match('~<span[^>]+id="productTitle"[^>]*>(.*?)</span>~si', $html, $m)) {
        $out['name'] = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES, 'UTF-8');
    }

    if (preg_match('~<meta[^>]+(?:property="og:description"|name="description")[^>]+content="([^"]+)"~i', $html, $m)) {
        $out['description'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }

    if (preg_match('~<meta[^>]+property="(?:product|og):price:amount"[^>]+content="([0-9.,]+)"~i', $html, $m)) {
        $out['price'] = trim($m[1]) . ' €';
    } elseif (preg_match('~"displayPrice":"([0-9.,]+)\s*[€E]?[^"]*"~i', $html, $m)) {
        $out['price'] = trim($m[1]) . ' €';
    } elseif (preg_match('~<span class="a-price-whole">([0-9.,]+)</span>\s*<span class="a-price-fraction">([0-9]+)</span>~', $html, $m)) {
        $out['price'] = $m[1] . ',' . $m[2] . ' €';
    } elseif (preg_match('~<span class="a-offscreen">\s*([0-9.,]+)\s*(?:[€E]|[A-Z]{2,3})?\s*</span>~i', $html, $m)) {
        $out['price'] = trim($m[1]) . ' €';
    }

    if (preg_match('~<meta[^>]+property="og:image"[^>]+content="([^"]+)"~i', $html, $m)) {
        $out['image'] = trim($m[1]);
    } else {
        // Amazon: la imagen principal viene primero en landingImage / "large" / "hiRes"
        if (preg_match('~id="landingImage"[^>]*src="([^"]+)"~i', $html, $m)) {
            $out['image'] = trim($m[1]);
        } elseif (preg_match('~"large":"(https://m\.media-amazon\.com/images/I/[^"]+)"~i', $html, $m)) {
            $out['image'] = trim($m[1]);
        } elseif (preg_match('~"hiRes":"(https://m\.media-amazon\.com/images/I/[^"]+)"~i', $html, $m)) {
            $out['image'] = trim($m[1]);
        } elseif (preg_match('~https://m\.media-amazon\.com/images/I/([A-Za-z0-9._-]+)\.~', $html, $m)) {
            $out['image'] = 'https://m.media-amazon.com/images/I/' . $m[1] . '._AC_SL1500_.jpg';
        }
    }
    return $out;
}

/* Descarga una imagen remota a uploads/<folder>.
 *
 * IMPORTANTE: usa http_request() y no cURL directamente. Antes lo usaba y en
 * los hostings sin la extensión cURL (o con cURL roto) eso era un error fatal
 * en blanco: la imagen no se importaba nunca. http_request() cae a los flujos
 * de PHP si no hay cURL, reintenta si el certificado está caducado y corta la
 * descarga en marcha si supera el tope. $referer se envía porque muchas webs
 * (Amazon, medios, CDNs) no sirven sus imágenes si la petición no parece venir
 * de su propia página.
 *
 * A diferencia de la antigua versión, que devolvía solo la ruta y se tragaba
 * el motivo del fallo, DEVUELVE EL PORQUÉ. Sin eso el administrador solo veía
 * un genérico «no se ha podido descargar» y no podía distinguir un 403 de un
 * certificado caducado o de un fichero de 9 MB. Misma forma que upload_file():
 * ['ok', 'path', 'error', 'warning'].
 *
 * $opt: min_w y min_h (px) rechazan la imagen ANTES de guardarla, para no
 * dejar en uploads/ un favicon de 32x32 que luego resulta inútil. */
function save_remote_image(string $url, string $folder, string $referer = '', array $opt = []): array {
    $no = ['ok' => false, 'path' => '', 'error' => '', 'warning' => '', 'too_small' => false];

    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        $no['error'] = 'La dirección de la imagen no es válida.';
        return $no;
    }
    if (strlen($url) > REMOTE_URL_MAX) {
        $no['error'] = 'La dirección de la imagen es demasiado larga.';
        return $no;
    }

    $minW = max(0, (int)($opt['min_w'] ?? 0));
    $minH = max(0, (int)($opt['min_h'] ?? 0));

    $headers = ['Accept: image/webp,image/apng,image/png,image/jpeg,image/gif,*/*;q=0.8'];
    if ($referer !== '') $headers[] = 'Referer: ' . $referer;

    $tmp = tempnam(sys_get_temp_dir(), 'imgdl');
    if ($tmp === false) {
        $no['error'] = 'No se pudo crear el fichero temporal para descargar la imagen.';
        return $no;
    }

    $r = http_request($url, [
        'to_file'   => $tmp,
        'timeout'   => 20,
        'max_bytes' => 4194304,   /* 4 MB: de sobra para un banner */
        'follow'    => true,
        'user_agent'=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'headers'   => $headers,
    ]);

    /* Motivo real del fallo, en orden de utilidad para el administrador. */
    if ($r['error'] !== '') {
        @unlink($tmp);
        $no['error']   = $r['error'];
        $no['warning'] = $r['warning'];
        return $no;
    }
    if ((int)$r['code'] !== 200) {
        @unlink($tmp);
        $no['error'] = 'El servidor remoto respondió con el código HTTP ' . (int)$r['code'] . '.';
        return $no;
    }
    if (!is_file($tmp) || (int)filesize($tmp) < 64) {
        @unlink($tmp);
        $no['error'] = 'El servidor remoto devolvió un archivo vacío o demasiado pequeño.';
        return $no;
    }

    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string)@finfo_file($finfo, $tmp) : '';
    if ($finfo && PHP_VERSION_ID < 80000) finfo_close($finfo);
    $info = @getimagesize($tmp);
    if (!in_array($mime, MIME_IMG, true) || !$info) {
        @unlink($tmp);
        $no['error'] = 'Lo que hay en esa dirección no es una imagen válida ('
            . ($mime !== '' ? $mime : 'tipo desconocido') . ').';
        return $no;
    }

    /* Tamaño comprobado ANTES de escribir nada en uploads/. */
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($minW > 0 || $minH > 0) {
        if ($w < $minW || $h < $minH) {
            @unlink($tmp);
            $no['error']     = 'Esa imagen es de ' . $w . ' × ' . $h . ' px y es demasiado pequeña.';
            $no['too_small'] = true;
            return $no;
        }
    }

    $name = bin2hex(random_bytes(8)) . '.' . str_replace('image/', '', $mime);
    $base = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($base)) @mkdir($base, 0775, true);
    if (!@rename($tmp, $base . '/' . $name)) {
        @unlink($tmp);
        $no['error'] = 'No se pudo guardar la imagen. Comprueba los permisos de la carpeta uploads/';
        return $no;
    }
    return ['ok' => true, 'path' => 'uploads/' . $folder . '/' . $name, 'error' => '', 'warning' => $r['warning'], 'too_small' => false];
}

/* ---------- Banner de publicidad ----------
 * Banner que el administrador coloca en la parte alta del área de trabajo.
 * Se gestiona introduciendo una URL: la app reconoce sola de dónde viene el
 * anuncio (sitio de origen, título, descripción e imagen), igual que hace
 * con los productos de la tienda.
 */

/* Nombre reconocible del sitio de origen (YouTube, Amazon, Instagram…). */
function known_site_name(string $host): string {
    $h = strtolower(trim($host));
    if ($h === '') return '';
    $h = preg_replace('#^www\d?\.#', '', $h);
    $map = [
        'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube',
        'instagram.com' => 'Instagram',
        'facebook.com' => 'Facebook', 'fb.watch' => 'Facebook',
        'tiktok.com' => 'TikTok',
        'twitter.com' => 'X (Twitter)', 'x.com' => 'X (Twitter)',
        'twitch.tv' => 'Twitch',
        'amazon.es' => 'Amazon', 'amazon.com' => 'Amazon', 'amazon.de' => 'Amazon', 'amzn.to' => 'Amazon',
        'aliexpress.com' => 'AliExpress', 'aliexpress.es' => 'AliExpress',
        'ebay.es' => 'eBay', 'ebay.com' => 'eBay',
        'wallapop.com' => 'Wallapop',
        'mil-anuncios.com' => 'Wallapop',
        'airsoftpro.es' => 'AirsoftPro',
        'airsoftfield.com' => 'Airsoft Field',
        'bsglab.com' => 'BSG',
        'hombretanque.com' => 'Hombre Tanque',
        'softair.es' => 'SoftAir',
    ];
    foreach ($map as $domain => $name) {
        if ($h === $domain || substr($h, -strlen('.' . $domain)) === '.' . $domain) return $name;
    }
    return '';
}

/* Identificador de vídeo de YouTube a partir de cualquier URL suya (o null). */
function youtube_video_id(string $url): ?string {
    $p = @parse_url($url);
    if (!$p || empty($p['host'])) return null;
    $host = strtolower(preg_replace('#^www\.#', '', $p['host']));
    $path = $p['path'] ?? '';
    if ($host === 'youtu.be') {
        $id = trim($path, '/');
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
    if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
            if (!empty($q['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $q['v'])) return $q['v'];
        }
        if (preg_match('~/(?:embed|v|shorts|live)/([A-Za-z0-9_-]{11})~', $path, $m)) return $m[1];
    }
    return null;
}

/* Lee el contenido de una etiqueta <meta> (property o name), en cualquier orden. */
function meta_content(string $html, string $key): string {
    $k = preg_quote($key, '~');
    $pats = [
        '~<meta[^>]+(?:property|name)="' . $k . '"[^>]*content="([^"]*)"~i',
        '~<meta[^>]+content="([^"]*)"[^>]+(?:property|name)="' . $k . '"~i',
    ];
    foreach ($pats as $p) {
        if (preg_match($p, $html, $m)) {
            $v = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            if ($v !== '') return $v;
        }
    }
    return '';
}

/* Quita el sufijo del nombre del sitio en un título: "Mi vídeo - YouTube" → "Mi vídeo". */
function clean_page_title(string $title): string {
    $t = trim(preg_replace('/\s+/u', ' ', $title));
    $t = preg_replace('~\s*[|\-–—·]\s*[^\|\-–—·]{2,45}$~u', '', $t);
    return trim($t) !== '' ? trim($t) : trim($title);
}

/* Convierte una URL relativa del HTML en absoluta usando el enlace de origen. */
function absolute_url(string $src, string $base): string {
    $src = trim($src);
    if ($src === '') return '';
    /* Una imagen metida en la página como "data:..." no se puede descargar ni
     * guardar: se descarta para no dejar un banner roto. */
    if (preg_match('#^data:#i', $src)) return '';
    if (preg_match('#^https?://#i', $src)) return $src;

    $p = @parse_url($base);
    $scheme = (is_array($p) && !empty($p['scheme'])) ? $p['scheme'] : 'https';
    /* "//cdn.ejemplo.com/img.png": hay que añadirle el esquema o la descarga
     * falla (esto pasaba con muchas webs y hacía que no se importara la imagen). */
    if (strpos($src, '//') === 0) return $scheme . ':' . $src;

    if (!$p || empty($p['host'])) return '';
    if (strpos($src, '/') === 0) return $scheme . '://' . $p['host'] . $src;
    $dir = rtrim(dirname($p['path'] ?? '/'), '/');
    return $scheme . '://' . $p['host'] . $dir . '/' . $src;
}

/* Lee el banner de una URL: título, descripción, imagen y ORIGEN de donde viene.
 * Devuelve ['title','description','image','image_kind','source','warning','error'].
 * image_kind dice de dónde salió la imagen ('og', 'apple' o 'favicon') para que
 * quien la usa pueda descartar un icono de 32x32. */
function fetch_banner_meta(string $url): array {
    $out = ['title' => '', 'description' => '', 'image' => '', 'image_kind' => '', 'source' => '', 'warning' => '', 'error' => ''];

    $host = (string)@parse_url($url, PHP_URL_HOST);
    $out['source'] = known_site_name($host) ?: $host;

    /* YouTube: la miniatura se puede construir sin necesidad de leer el HTML. */
    $yt = youtube_video_id($url);
    if ($yt !== null) {
        $out['source'] = 'YouTube';
        $out['image'] = 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
        $out['image_kind'] = 'og';
        $out['title'] = 'Vídeo en YouTube';
    }

    [$code, $html] = http_get_like_browser($url);
    if ($code !== 200 || $html === '') {
        if ($out['image'] === '') {
            $out['error'] = 'No se pudo abrir el enlace (código ' . $code . '). Comprueba la URL o rellena el título y la imagen a mano.';
        } else {
            $out['warning'] = 'No se pudo leer la página: se usan el origen y la miniatura detectados. Ajusta el título si quieres.';
        }
        return $out;
    }

    $title = meta_content($html, 'og:title') ?: meta_content($html, 'twitter:title');
    if ($title === '' && preg_match('~<h1[^>]*>(.*?)</h1>~si', $html, $m)) {
        $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')));
    }
    if ($title === '' && preg_match('~<title[^>]*>(.*?)</title>~si', $html, $m)) {
        $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')));
    }
    $out['title'] = $title !== '' ? clean_page_title($title) : $out['title'];

    $desc = meta_content($html, 'og:description') ?: meta_content($html, 'description') ?: meta_content($html, 'twitter:description');
    $out['description'] = $desc !== '' ? trim(preg_replace('/\s+/u', ' ', $desc)) : '';

    /* Imagen: la de OpenGraph, si no la de Twitter, si no el icono de Apple, si no
     * el favicon. Se marca de dónde salió (image_kind) porque el favicon es un
     * icono de 32x32: en un banner de 728x90 se ve fatal, así que quien lo usa
     * lo descarta y avisa de que suba él una imagen. */
    $kind = '';
    $img = meta_content($html, 'og:image') ?: meta_content($html, 'twitter:image');
    if ($img !== '') $kind = 'og';
    if ($img === '' && preg_match('~<link[^>]+rel="[^"]*image_src[^"]*"[^>]+href="([^"]+)"~i', $html, $m)) {
        $img = trim($m[1]); $kind = 'og';
    }
    if ($img === '' && preg_match('~<link[^>]+rel="[^"]*apple-touch-icon[^"]*"[^>]+href="([^"]+)"~i', $html, $m)) {
        $img = trim($m[1]); $kind = 'apple';
    }
    if ($img === '' && $host !== '' && $yt === null) {
        $img = 'https://' . $host . '/favicon.ico'; $kind = 'favicon';
    }
    $out['image'] = $img !== '' ? absolute_url($img, $url) : $out['image'];
    $out['image_kind'] = $kind;

    $site = meta_content($html, 'og:site_name');
    if ($site !== '') $out['source'] = clean_page_title($site);
    elseif ($out['source'] === '' || $out['source'] === $host) $out['source'] = $host;

    /* Si no hemos sacado nada en claro, la web seguramente nos bloqueó. */
    if ($out['title'] === '' && $out['image'] === '') {
        $out['error'] = 'La web bloqueó la lectura automática del enlace (verificación anti-bots). Rellena el título y la imagen a mano.';
    }

    return $out;
}

/* Banners de publicidad guardados (orden de visualización). */
function ad_banners(): array {
    try {
        return db()->query('SELECT * FROM ' . t('ad_banners') . ' ORDER BY sort_order ASC, id DESC')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/* Banner visible en la parte alta del área de trabajo (el de menor orden). */
function active_ad_banner(): ?array {
    $all = ad_banners();
    foreach ($all as $b) {
        if ((int)$b['active'] === 1) return $b;
    }
    return null;
}

/* URL de destino del anuncio válida (solo http/https). */
function ad_banner_link(?array $b): ?string {
    $u = trim((string)($b['url'] ?? ''));
    if ($u === '' || !preg_match('#^https?://#i', $u)) return null;
    return $u;
}

/* ===== Imagen del banner: saber how de apaisada es =====
 * Un banner 728x90 y un logotipo cuadrado no se pueden enseñar igual: si se
 * siempre se recorta al mismo lado, el banner se ve cortado y el logo se ve
 * destrozado. Así que se mide la imagen una vez y se guarda el resultado; en
 * cada visita solo se lee de la caché (nada de descargas).
 * Devuelve [ancho, alto]; [0,0] si no se ha podido saber.
 */
function banner_image_size(string $img): array {
    static $cache = null;
    if ($cache === null) $cache = (array)update_state_read('banner_img_wh', []);
    if (!is_array($cache)) $cache = [];
    if (isset($cache[$img])) {
        $v = explode('x', (string)$cache[$img]);
        return [(int)$v[0], (int)$v[1]];
    }
    $wh = measure_image_size($img);
    if ($wh[0] > 0 && $wh[1] > 0) {
        $cache[$img] = $wh[0] . 'x' . $wh[1];
        if (count($cache) > 60) $cache = array_slice($cache, -60, null, true);
        update_state_write(['banner_img_wh' => $cache]);
    }
    return $wh;
}

/* Mide de verdad la imagen: archivo local con getimagesize, o descargando solo
 * lo justo (1 MB) si es remota. Nunca lanza errores. */
function measure_image_size(string $img): array {
    $img = trim($img);
    if ($img === '' || strlen($img) > REMOTE_URL_MAX) return [0, 0];

    if (strpos($img, 'http') !== 0) {
        /* Imagen subida por el admin: está en uploads/. */
        $rel = ltrim(str_replace('..', '', $img), '/');
        $path = dirname(__DIR__) . '/' . $rel;
        if (!is_file($path)) return [0, 0];
        $s = @getimagesize($path);
        return ($s && (int)$s[0] > 0) ? [(int)$s[0], (int)$s[1]] : [0, 0];
    }

    $r = http_request($img, [
        'timeout' => 8, 'max_bytes' => 1048576, 'follow' => true,
        'user_agent' => 'AirsoftSocial/1.5 (lectura de imagen de banner)',
        'headers' => ['Accept: image/*'],
    ]);
    if (empty($r['body'])) return [0, 0];
    $s = @getimagesizefromstring($r['body']);
    return ($s && (int)$s[0] > 0) ? [(int)$s[0], (int)$s[1]] : [0, 0];
}

/* ¿La imagen tiene forma de banner (apaisada de verdad) o es más bien un logo o
 * una foto? El banner es 728x90, o sea 8.09:1. Solo a partir de 7.5:1 la imagen
 * da la ventaja de ocupar los 728x90 enteros sin que se le recorte un trozo
 * apreciable; cualquier otra cosa se enseña entera a su lado, sin recortar. */
function banner_image_is_wide(string $img): bool {
    [$w, $h] = banner_image_size($img);
    return $w > 0 && $h > 0 && ($w / $h) >= 7.5;
}

/* Dibuja el banner de publicidad al inicio del área de trabajo ('' si no hay).
 * La imagen se adapta a su proporción: si es apaisada ocupa los 728x90 enteros
 * con el texto encima; si no, se muestra entera como miniatura a la izquierda.
 * $only dibuja un banner concreto en vez del visible (vista previa y pruebas). */
function ad_banner_html(?array $only = null): string {
    $b = $only ?: active_ad_banner();
    if (!$b) return '';
    $link = ad_banner_link($b);
    $title = trim((string)($b['title'] ?? ''));
    $img = trim((string)($b['image'] ?? ''));
    /* Solo se oculta si no hay nada que hacer clic ni nada que enseñar. Como el
     * enlace es obligatorio, un anuncio sin título (solo imagen) se muestra
     * igual: aquí no se descarta por falta de título. */
    if ($title === '' && !$link && $img === '') return '';
    $wide = false;
    if ($img !== '' && preg_match('#^(https?://|uploads/)#i', $img)) $wide = banner_image_is_wide($img);

    $h = '<aside class="ad-banner' . ($wide ? ' ad-banner-wide' : '') . '" aria-label="Publicidad">';
    $inner = '';
    if ($img !== '' && preg_match('#^(https?://|uploads/)#i', $img)) {
        /* Sin loading="lazy": la altura del banner depende de la imagen y el
         * navegador no la cargaba (se quedaba a 0 px de alto). */
        if ($wide) {
            $inner .= '<span class="ad-banner-cover"><img src="' . e($img) . '" alt="' . e($title) . '"></span>';
        } else {
            $inner .= '<div class="ad-banner-img"><img src="' . e($img) . '" alt="' . e($title) . '"></div>';
        }
    }
    $desc = trim((string)($b['description'] ?? ''));
    /* Sin etiquetas ni «Ver el anuncio →»: el banner es un aviso publicitario
     * y no tiene por qué ir rotulado. Solo se enseña el contenido (título y
     * descripción) y el enlace es el banner entero, así que con pulsar en
     * cualquier sitio se abre el anuncio. */
    $body = ($title !== '' ? '<strong class="ad-banner-title">' . e($title) . '</strong>' : '')
          . ($desc !== '' ? '<p class="ad-banner-desc">' . e($desc) . '</p>' : '');
    if ($body !== '') $inner .= '<div class="ad-banner-body">' . $body . '</div>';

    $h .= $link
        ? '<a class="ad-banner-link" href="' . e($link) . '" target="_blank" rel="noopener nofollow sponsored">' . $inner . '</a>'
        : '<div class="ad-banner-link">' . $inner . '</div>';
    if (is_admin()) $h .= '<a class="ad-banner-admin" href="banner_admin.php" title="Gestionar anuncios">⚙️</a>';
    return $h . '</aside>';
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

/* ---------- Auto-actualización ----------
 * La instalación comprueba periódicamente GitHub (laguna de confianza: el
 * repost gields `GITHUB_REPO`, por defecto el oficial). Cuando hay una
 * release más moderna que `version.txt` se avisa al administrador y se
 * ofrece aplicar la actualización desde el panel (actions/update.php).
 */

function update_github_repo(): string {
    return defined('GITHUB_REPO') && GITHUB_REPO ? (string)GITHUB_REPO : 'JMBermejias/airsoftsocial';
}

function update_check_hours(): int {
    return defined('UPDATE_CHECK_HOURS') ? (int)UPDATE_CHECK_HOURS : 6;
}

function app_version(): string {
    static $v = null;
    if ($v === null) {
        $f = __DIR__ . '/../version.txt';
        $v = is_file($f) ? trim((string)file_get_contents($f)) : '0.0.0';
    }
    return $v;
}

/* Estado interno del actualizador dentro de uploads/ (no tocarlo al actualizar). */
function update_state_dir(): string {
    $dir = dirname(__DIR__) . '/uploads/_system';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function update_state_read(string $key, $default = null) {
    $f = update_state_dir() . '/state.json';
    $data = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($data) && array_key_exists($key, $data) ? $data[$key] : $default;
}

function update_state_write(array $patch): void {
    $f = update_state_dir() . '/state.json';
    $data = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    if (!is_array($data)) $data = [];
    foreach ($patch as $k => $v) $data[$k] = $v;
    @file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function update_log(string $tag, string $event, string $detail = ''): void {
    $f = update_state_dir() . '/log.json';
    $log = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    if (!is_array($log)) $log = [];
    array_unshift($log, ['date' => date('Y-m-d H:i:s'), 'tag' => $tag, 'event' => $event, 'detail' => $detail]);
    $log = array_slice($log, 0, 20);
    @file_put_contents($f, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/* Token de GitHub opcional (config.php o variable de entorno). Necesario si
 * el repositorio de las actualizaciones es privado: sin token, GitHub responde
 * 404 a cualquier installation que no sea la del dueño. */
function gh_token(): string {
    if (defined('GITHUB_TOKEN') && trim((string)GITHUB_TOKEN) !== '') return trim((string)GITHUB_TOKEN);
    if (!empty($_ENV['GITHUB_TOKEN'])) return trim((string)$_ENV['GITHUB_TOKEN']);
    return '';
}

/* Explica por qué GitHub no devolvió la release (para mostrarlo al admin). */
function gh_private_repo_hint(int $code = 0): string {
    $repo = update_github_repo();
    if (gh_token() === '') {
        return 'El repositorio ' . $repo . ' es privado y esta instalación no tiene token, así que GitHub no enseña las releases (código ' . $code . '). '
             . 'Solución: añade GITHUB_TOKEN en config.php (token de solo lectura) o deja el repositorio en público.';
    }
    return 'El token de GitHub de config.php no tiene acceso al repositorio ' . $repo . ' (código ' . $code . '). Revisa que el token no haya caducado.';
}

/* Última release sin gastar la API de GitHub (la API limita a 60/h por IP,
 * algo habitual en hosting compartido): /releases/latest responde con un
 * redirect a /releases/tag/vX.Y.Z y ese redirect nos da la versión.
 * Devuelve ['tag' => 'v1.2.3'|'' , 'error' => ''|motivo]. */
function gh_latest_tag(): array {
    $h = [];
    if (gh_token() !== '') $h[] = 'Authorization: Bearer ' . gh_token();
    $r = http_request('https://github.com/' . update_github_repo() . '/releases/latest', [
        'headers'     => $h,
        'follow'      => false,   /* solo queremos ver la cabecera Location */
        'timeout'     => 12,
        'user_agent'  => 'AirsoftSocial-Update',
    ]);
    if ($r['code'] === 404) return ['tag' => '', 'error' => gh_private_repo_hint(404)];
    if ($r['code'] === 403 || $r['code'] === 429) {
        return ['tag' => '', 'error' => 'GitHub ha limitado las peticiones desde tu servidor (60/h por IP). Añade GITHUB_TOKEN en config.php o espera unos minutos.'];
    }
    if ($r['error'] !== '' && $r['code'] === 0) return ['tag' => '', 'error' => $r['error']];
    foreach ((array)($r['headers']['location'] ?? []) as $loc) {
        if (preg_match('#/releases/tag/(v\d+\.\d+\.\d+)#', (string)$loc, $m)) {
            return ['tag' => $m[1], 'error' => ''];
        }
    }
    return ['tag' => '', 'error' => 'GitHub no devolvió la última release (código HTTP ' . $r['code'] . ').'];
}

/* Última release vía API de GitHub (trae nombre y notas de la versión).
 * Devuelve ['ok','error','tag','name','body','published_at','code','warning']. */
function gh_api_latest(): array {
    $out = ['ok' => false, 'error' => '', 'tag' => '', 'name' => '', 'body' => '', 'published_at' => '', 'code' => 0, 'warning' => ''];
    $h = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    if (gh_token() !== '') $h[] = 'Authorization: Bearer ' . gh_token();
    $r = http_request('https://api.github.com/repos/' . update_github_repo() . '/releases/latest', [
        'headers'     => $h,
        'timeout'     => 12,
        'user_agent'  => 'AirsoftSocial-Update',
    ]);
    $out['code']    = $r['code'];
    $out['warning'] = $r['warning'];
    if ($r['code'] === 404)  { $out['error'] = gh_private_repo_hint(404); return $out; }
    if ($r['code'] === 401)  { $out['error'] = 'El token de GitHub de config.php no es válido.'; return $out; }
    if ($r['code'] === 403 || $r['code'] === 429) {
        $out['error'] = 'GitHub ha limitado las peticiones desde tu servidor (60/h por IP). Añade GITHUB_TOKEN en config.php o espera unos minutos.';
        return $out;
    }
    if ($r['error'] !== '')  { $out['error'] = $r['error']; return $out; }
    $d = json_decode($r['body'], true);
    if (!is_array($d) || empty($d['tag_name'])) {
        $out['error'] = 'GitHub devolvió una respuesta que no se entiende (código HTTP ' . $r['code'] . ').';
        return $out;
    }
    $out['ok']           = true;
    $out['tag']          = (string)$d['tag_name'];
    $out['name']         = (string)($d['name'] ?? $d['tag_name']);
    $out['body']         = (string)($d['body'] ?? '');
    $out['published_at'] = (string)($d['published_at'] ?? '');
    return $out;
}

/* Última release publicada en GitHub (cacheada N horas). Nunca lanza errores. */
function latest_release(bool $force = false): array {
    $cache = update_state_read('release', null);
    if (!$force && is_array($cache) && time() - (int)update_state_read('checked_at', 0) < update_check_hours() * 3600) {
        return $cache;
    }
    $api = gh_api_latest();
    if (!empty($api['ok']) && preg_match('/^v?\d+\.\d+\.\d+$/', (string)$api['tag'])) {
        $result = [
            'ok'           => true,
            'tag'          => (string)$api['tag'],
            'name'         => (string)$api['name'],
            'body'         => (string)$api['body'],
            'published_at' => (string)$api['published_at'],
        ];
        if (!empty($api['warning'])) $result['warning'] = $api['warning'];
    } else {
        /* Plan B: el redirect de /releases/latest no gasta cuota de la API. */
        $alt = gh_latest_tag();
        if ($alt['tag'] !== '') {
            $result = ['ok' => true, 'tag' => $alt['tag'], 'name' => $alt['tag'], 'body' => '', 'published_at' => ''];
        } else {
            $result = ['ok' => false, 'error' => (string)$api['error'] !== '' ? $api['error'] : $alt['error']];
        }
    }
    update_state_write(['release' => $result, 'checked_at' => time()]);
    return $result;
}

/* Descarga un archivo de GitHub (codeload) a disco, con token si hace falta.
 * Devuelve ['ok' => bool, 'error' => ...] (el archivo queda solo si ok). */
function gh_download(string $url, string $file, int $maxBytes = 209715200): array {
    $h = [];
    if (gh_token() !== '') $h[] = 'Authorization: Bearer ' . gh_token();
    $r = http_request($url, [
        'headers'     => $h,
        'to_file'     => $file,
        'max_bytes'   => $maxBytes,
        'timeout'     => 120,
        'user_agent'  => 'AirsoftSocial-Update',
    ]);
    if ($r['error'] !== '' || $r['code'] >= 400) {
        @unlink($file);
        $err = $r['error'] !== '' ? $r['error'] : 'GitHub respondió con el código HTTP ' . $r['code'] . '.';
        return ['ok' => false, 'error' => $err, 'warning' => $r['warning']];
    }
    return ['ok' => true, 'error' => '', 'warning' => $r['warning']];
}

/* Diagnóstico del servidor para la pantalla de actualizaciones: qué falta
 * para poder hablar con GitHub. Con $live=true hace una prueba real. */
function update_diagnostics(bool $live = false): array {
    $d = [
        'php'              => PHP_VERSION,
        'curl'             => function_exists('curl_init'),
        'allow_url_fopen'  => (bool)ini_get('allow_url_fopen'),
        'openssl'          => extension_loaded('openssl'),
        'repo'             => update_github_repo(),
        'token'            => gh_token() !== '',
        'instalada'        => app_version(),
        'conexion'         => '',
    ];
    if (!$live) return $d;
    $h = [];
    if (gh_token() !== '') $h[] = 'Authorization: Bearer ' . gh_token();
    $r = http_request('https://api.github.com/repos/' . update_github_repo(), [
        'headers'    => $h,
        'timeout'    => 12,
        'user_agent' => 'AirsoftSocial-Update',
    ]);
    if ($r['code'] === 200) {
        $d['conexion'] = '✅ GitHub responde y esta instalación puede leer ' . update_github_repo() . ' (vía ' . $r['via'] . ')';
    } elseif ($r['code'] === 404) {
        $d['conexion'] = '❌ ' . gh_private_repo_hint(404) . ' (vía ' . $r['via'] . ')';
    } elseif ($r['code'] === 403 || $r['code'] === 429) {
        $d['conexion'] = '❌ GitHub ha limitado las peticiones desde tu servidor (60/h por IP) (vía ' . $r['via'] . ')';
    } elseif ($r['error'] !== '') {
        $d['conexion'] = '❌ ' . $r['error'] . ' (vía ' . $r['via'] . ')';
    } else {
        $d['conexion'] = '❌ GitHub respondió con el código HTTP ' . $r['code'] . ' (vía ' . $r['via'] . ')';
    }
    return $d;
}

/* Devuelve los datos de una actualización disponible (o null si no hay). */
function update_available(): ?array {
    if (!is_admin()) return null;
    $rel = latest_release();
    if (empty($rel['ok']) || !preg_match('/^v\d+\.\d+\.\d+$/', $rel['tag'])) return null;
    $cur = app_version();
    if (version_compare(ltrim($rel['tag'], 'v'), $cur, '<=')) return null;
    return ['current' => $cur, 'latest' => ltrim($rel['tag'], 'v'), 'tag' => $rel['tag'], 'name' => $rel['name'], 'body' => $rel['body']];
}

/* Avisa a los administradores (campanilla) una sola vez por versión. */
function update_notify_admins(): void {
    if (!is_admin()) return;
    $rel = latest_release();
    if (empty($rel['ok']) || !preg_match('/^v\d+\.\d+\.\d+$/', $rel['tag'])) return;
    $latest = ltrim($rel['tag'], 'v');
    if (version_compare($latest, app_version(), '<=')) return;
    $key = 'notified_' . $latest;
    if (update_state_read($key, false)) return;
    $sent = false;
    try {
        $stmt = db()->query('SELECT id FROM ' . t('users') . ' WHERE is_admin = 1');
        foreach ($stmt->fetchAll() as $u) {
            try {
                notify((int)$u['id'], null, 'update', 'Nueva versión ' . $latest . ' disponible. ¡Actualiza!', 'updates.php');
                $sent = true;
            } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
    if ($sent) update_state_write([$key => true]);
}

/* === Autoparchado del esquema ===
 * En un hosting compartido la base de datos no siempre está a la par que el
 * código: si se sube el código a mano (o falla una actualización) las tablas
 * nuevas no aparecen y la web responde con un error 500 en blanco.
 *
 * IMPORTANTE: esta función se llama en CADA página, así que debe ser
 * muchísimo más barata que una migración. Por eso:
 *   - solo se ejecuta si se detecta que falta algo (una consulta rápida), y
 *     como mucho una vez cada SCHEMA_CHECK_HOURS horas;
 *   - solo crea tablas con CREATE TABLE IF NOT EXISTS, nunca hace ALTER TABLE
 *     (reconstruir una tabla en cada visita agotaba el servidor: 502);
 *   - nunca lanza excepciones.
 * Devuelve [] si todo está bien, o ['db' => explicación] si no puede arreglarlo.
 */
function ensure_schema(bool $force = false): array {
    static $done = false;
    if ($done) return [];
    $done = true;

    /* Solo administradores: una comprobación en cada visita de cada usuario
     * es gastarse el servidor. Los que guardan datos (actions/*.php) la fuerzan. */
    if (!$force && !is_admin()) return [];

    $hours = 6;
    if (defined('SCHEMA_CHECK_HOURS') && (int)SCHEMA_CHECK_HOURS > 0) $hours = (int)SCHEMA_CHECK_HOURS;

    /* Comprobación rápida: ¿existe ya la tabla de anuncios? Si sí, no hay nada
     * que hacer y no se toca nada más (una sola consulta, muy barata). */
    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM ' . t('ad_banners') . ' LIMIT 1');
        return [];
    } catch (Throwable $e) {
        /* Sigue: puede ser que falte la tabla o que no haya conexión. */
    }

    /* No se reintenta en cada visita: si ya se intentó y falló, se espera. */
    if (!$force) {
        $last = (int)update_state_read('schema_checked_at', 0);
        if ($last > 0 && time() - $last < $hours * 3600) {
            return (array)update_state_read('schema_issue', []);
        }
    }
    update_state_write(['schema_checked_at' => time(), 'schema_issue' => []]);

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return schema_issue('No se pudo conectar con la base de datos. Revisa config.php o pídele a tu hosting que la revise.');
    }

    /* Solo CREATE TABLE IF NOT EXISTS: barato y sin riesgo de bloquear datos. */
    try {
        foreach (schema_tables_sql() as $sql) {
            $pdo->exec($sql);
        }
    } catch (Throwable $e) {
        return schema_issue('Tu usuario de MySQL no puede crear tablas (no está permitido en este hosting). '
            . 'Pídeselo a tu hosting o crea tú la tabla en phpMyAdmin:  ' . schema_ad_banners_sql());
    }

    /* Comprobación posterior. */
    try {
        $pdo->query('SELECT 1 FROM ' . t('ad_banners') . ' LIMIT 1');
        return [];
    } catch (Throwable $e) {
        return schema_issue('La base de datos está desfasada y no se puede arreglar automáticamente. '
            . 'Crea esta tabla en phpMyAdmin:  ' . schema_ad_banners_sql());
    }
}

/* Guarda el aviso y lo devuelve, para que se muestre en la web. */
function schema_issue(string $msg): array {
    update_state_write(['schema_issue' => ['db' => $msg]]);
    return ['db' => $msg];
}

/* Sentido de la tabla de anuncios para crear a mano. */
function schema_ad_banners_sql(): string {
    return 'CREATE TABLE `' . DB_PREFIX . 'ad_banners` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
        . '`title` VARCHAR(160) NOT NULL, `description` VARCHAR(400) DEFAULT NULL, `image` VARCHAR(255) DEFAULT NULL, '
        . '`url` VARCHAR(300) NOT NULL, `source` VARCHAR(120) DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1, '
        . '`sort_order` INT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) '
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
}

/* Tablas nuevas que puede crear el autoparchado (solo CREATE TABLE IF NOT
 * EXISTS: nada de ALTER TABLE aquí, para no bloquear la web en cada visita). */
function schema_tables_sql(): array {
    return [
        'CREATE TABLE IF NOT EXISTS ' . t('ad_banners') . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            description VARCHAR(400) DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            url VARCHAR(300) NOT NULL COMMENT "Enlace de destino del anuncio (http/https)",
            source VARCHAR(120) DEFAULT NULL COMMENT "Origen detectado del anuncio (nombre del sitio)",
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS ' . t('payment_methods') . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            method_key VARCHAR(30) NOT NULL UNIQUE,
            label VARCHAR(80) NOT NULL,
            icon VARCHAR(8) DEFAULT NULL,
            meta VARCHAR(255) DEFAULT NULL COMMENT "Dato público del método (URL PayPal, teléfono Bizum, IBAN, nota)",
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
}

/* Migraciones de esquema compartidas por install.php y el auto-actualizador.
 * Se ejecutan siempre: deben ser idempotentes. */
function run_migrations(PDO $pdo): void {
    /* Historias: columna para vídeo (granulada como idempotente). */
    try {
        $pdo->exec('ALTER TABLE ' . t('stories') . ' ADD COLUMN video VARCHAR(255) DEFAULT NULL AFTER image');
    } catch (PDOException $e) { /* ya añadida */ }

    /* Métodos de pago de la tienda (afiliación). */
    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . t('payment_methods') . ' (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        method_key VARCHAR(30) NOT NULL UNIQUE,
        label VARCHAR(80) NOT NULL,
        icon VARCHAR(8) DEFAULT NULL,
        meta VARCHAR(255) DEFAULT NULL COMMENT "Dato público del método (URL PayPal, teléfono Bizum, IBAN, nota)",
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $seed = [
        ['paypal', 'PayPal', '🅿️', 'https://paypal.me/tu_usuario', 1, 1],
        ['bizum', 'Bizum', '📱', '600 000 000', 1, 2],
        ['card', 'Tarjeta de crédito / débito', '💳', 'Visa · Mastercard', 1, 3],
        ['bank', 'Transferencia bancaria', '🏦', 'ES00 0000 0000 0000 0000 0000', 1, 4],
        ['cash', 'Efectivo / recogida en campo', '💵', 'Abona en la entrega', 1, 5],
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO ' . t('payment_methods') . ' (method_key, label, icon, meta, is_enabled, sort_order) VALUES (?,?,?,?,?,?)');
    foreach ($seed as $s) $ins->execute($s);

    /* Banner de publicidad del área de trabajo. */
    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . t('ad_banners') . ' (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        description VARCHAR(400) DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        url VARCHAR(300) NOT NULL COMMENT "Enlace de destino del anuncio (http/https)",
        source VARCHAR(120) DEFAULT NULL COMMENT "Origen detectado del anuncio (nombre del sitio)",
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}