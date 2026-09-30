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

/* Abre una URL imitando un navegador real (UA, Accept-Language, Referer).
 * Devuelve [código HTTP, contenido HTML] o [0/null]. */
function http_get_like_browser(string $url): array {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Referer: https://www.amazon.es/',
        ],
    ]);
    $html = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80000) curl_close($ch);
    return [$code, $html === false ? '' : (string)$html];
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

/* Descarga una imagen remota dentro de uploads/ y devuelve la ruta local, o null. */
function save_remote_image(string $url, string $folder): ?string {
    if (!preg_match('#^https?://#i', $url)) return null;
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => $ua,
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80000) curl_close($ch);
    if ($data === false || $code !== 200 || $data === '') return null;

    $tmp = tempnam(sys_get_temp_dir(), 'prodimg');
    if ($tmp === false) return null;
    file_put_contents($tmp, $data);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    if (PHP_VERSION_ID < 80000) finfo_close($finfo);
    if (!in_array($mime, MIME_IMG, true)) { @unlink($tmp); return null; }

    $ext = str_replace('image/', '', $mime);
    if ($ext === 'svg+xml') $ext = 'svg';
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $base = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($base)) mkdir($base, 0775, true);
    if (!rename($tmp, $base . '/' . $name)) { @unlink($tmp); return null; }
    return 'uploads/' . $folder . '/' . $name;
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
    if ($src === '' || preg_match('#^(https?:)?//#i', $src)) return $src;
    $p = @parse_url($base);
    if (!$p || empty($p['host'])) return $src;
    $scheme = $p['scheme'] ?? 'https';
    if (strpos($src, '/') === 0) return $scheme . '://' . $p['host'] . $src;
    $dir = rtrim(dirname($p['path'] ?? '/'), '/');
    return $scheme . '://' . $p['host'] . $dir . '/' . $src;
}

/* Lee el banner de una URL: título, descripción, imagen y ORIGEN de donde viene.
 * Devuelve ['title','description','image','source','warning','error']. */
function fetch_banner_meta(string $url): array {
    $out = ['title' => '', 'description' => '', 'image' => '', 'source' => '', 'warning' => '', 'error' => ''];

    $host = (string)@parse_url($url, PHP_URL_HOST);
    $out['source'] = known_site_name($host) ?: $host;

    /* YouTube: la miniatura se puede construir sin necesidad de leer el HTML. */
    $yt = youtube_video_id($url);
    if ($yt !== null) {
        $out['source'] = 'YouTube';
        $out['image'] = 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
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

    /* Imagen: la de OpenGraph, si no la de Twitter, si no el icono de Apple, si no el favicon. */
    $img = meta_content($html, 'og:image') ?: meta_content($html, 'twitter:image');
    if ($img === '' && preg_match('~<link[^>]+rel="[^"]*apple-touch-icon[^"]*"[^>]+href="([^"]+)"~i', $html, $m)) {
        $img = trim($m[1]);
    }
    if ($img === '' && $host !== '' && $yt === null) {
        $img = 'https://' . $host . '/favicon.ico';
    }
    $out['image'] = $img !== '' ? absolute_url($img, $url) : $out['image'];

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

/* Dibuja el banner de publicidad al inicio del área de trabajo ('' si no hay). */
function ad_banner_html(): string {
    $b = active_ad_banner();
    if (!$b) return '';
    $link = ad_banner_link($b);
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '' && !$link) return '';

    $img = trim((string)($b['image'] ?? ''));
    $tag = trim((string)($b['source'] ?? ''));
    $h = '<aside class="ad-banner" aria-label="Publicidad">';
    $inner = '';
    if ($img !== '' && preg_match('#^(https?://|uploads/)#i', $img)) {
        /* Sin loading="lazy": la altura del banner depende de la imagen y el
         * navegador no la cargaba (se quedaba a 0 px de alto). */
        $inner .= '<div class="ad-banner-img"><img src="' . e($img) . '" alt="' . e($title) . '"></div>';
    }
    $desc = trim((string)($b['description'] ?? ''));
    $inner .= '<div class="ad-banner-body">'
        . '<span class="ad-banner-tag">📢 Publicidad' . ($tag !== '' ? ' · ' . e($tag) : '') . '</span>'
        . ($title !== '' ? '<strong class="ad-banner-title">' . e($title) . '</strong>' : '')
        . ($desc !== '' ? '<p class="ad-banner-desc">' . e(mb_strimwidth($desc, 0, 180, '…')) . '</p>' : '')
        . ($link ? '<span class="ad-banner-cta">Ver el anuncio →</span>' : '')
        . '</div>';

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

/* Última release sin gastar la API de GitHub (la API limita a 60/h por IP,
 * algo habitual en hosting compartido): /releases/latest responde con un
 * redirect a /releases/tag/vX.Y.Z y ese redirect nos da la versión. */
function gh_latest_tag(): ?string {
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "User-Agent: AirsoftSocial-Update\r\n",
        'ignore_errors' => true,
        'follow_location' => 0,
        'max_redirects' => 0,
    ]]);
    $headers = @get_headers('https://github.com/' . update_github_repo() . '/releases/latest', 1, $ctx);
    if (!is_array($headers)) return null;
    foreach ($headers as $k => $v) {
        if (strcasecmp((string)$k, 'Location') !== 0) continue;
        foreach (is_array($v) ? $v : [$v] as $loc) {
            if (preg_match('#/releases/tag/(v\d+\.\d+\.\d+)#', (string)$loc, $m)) return $m[1];
        }
    }
    return null;
}

/* Última release vía API de GitHub. Usa GITHUB_TOKEN (opcional en config.php)
 * para evitar el límite de 60 peticiones/h por IP en hosting compartido. */
function gh_api_latest(): ?array {
    $h = "User-Agent: AirsoftSocial-Update\r\nAccept: application/vnd.github+json\r\n";
    if (defined('GITHUB_TOKEN') && GITHUB_TOKEN !== '') {
        $h .= 'Authorization: Bearer ' . GITHUB_TOKEN . "\r\n";
    }
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 12, 'header' => $h, 'ignore_errors' => true]]);
    $raw = @file_get_contents('https://api.github.com/repos/' . update_github_repo() . '/releases/latest', false, $ctx);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['tag_name'])) return null;
    return [
        'tag' => (string)$d['tag_name'],
        'name' => (string)($d['name'] ?? $d['tag_name']),
        'body' => (string)($d['body'] ?? ''),
        'published_at' => (string)($d['published_at'] ?? ''),
    ];
}

/* Última release publicada en GitHub (cacheada N horas). Nunca lanza errores. */
function latest_release(bool $force = false): array {
    $cache = update_state_read('release', null);
    if (!$force && is_array($cache) && time() - (int)update_state_read('checked_at', 0) < update_check_hours() * 3600) {
        return $cache;
    }
    $result = gh_api_latest();
    if (!$result) {
        $tag = gh_latest_tag();
        if ($tag) $result = ['tag' => $tag, 'name' => $tag, 'body' => '', 'published_at' => ''];
    }
    if (!$result) {
        $result = ['ok' => false, 'error' => 'No se pudo contactar con GitHub.'];
    } else {
        $result['ok'] = true;
    }
    update_state_write(['release' => $result, 'checked_at' => time()]);
    return $result;
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