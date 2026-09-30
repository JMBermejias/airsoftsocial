<?php
/**
 * Diagnóstico de la instalación.
 *
 * Esta página NO necesita base de datos ni sesión, a propósito: sirve para
 * saber qué pasa cuando la web no carga (pantalla en blanco, error 500 o 502)
 * y no se puede entrar al panel. No muestra datos personales ni credenciales.
 *
 * Puedes borrarla cuando quieras: no es necesaria para que la app funcione.
 */
header('Content-Type: text/html; charset=utf-8');

/* Ajustes que se pueden probar desde aquí sin tocar nada. */
$repo    = is_file(__DIR__ . '/config.php') && preg_match("/GITHUB_REPO',\s*'([^']+)'/", (string)@file_get_contents(__DIR__ . '/config.php'), $m)
    ? $m[1] : 'JMBermejias/airsoftsocial';
$version = is_file(__DIR__ . '/version.txt') ? trim((string)file_get_contents(__DIR__ . '/version.txt')) : '—';
$files   = ['index.php', 'includes/functions.php', 'includes/header.php', 'schema.sql', 'service-worker.js'];
$checks  = [];
$bad     = 0;

/* 1) PHP y extensiones */
$checks['Versión de PHP'] = [PHP_VERSION, true];
$checks['Extensión cURL'] = [function_exists('curl_init') ? 'disponible' : 'NO disponible (afecta al actualizador)', function_exists('curl_init')];
$checks['allow_url_fopen'] = [ini_get('allow_url_fopen') ? 'activado' : 'desactivado (sin cURL no se puede salir a Internet)', (bool)ini_get('allow_url_fopen') || function_exists('curl_init')];
$checks['Límite de ejecución'] = [ini_get('max_execution_time') . ' s', true];
$checks['Memoria máxima'] = [ini_get('memory_limit'), true];

/* 2) Archivos esenciales presentes y con sintaxis válida */
$syntaxBad = [];
foreach ($files as $f) {
    if (!is_file(__DIR__ . '/' . $f)) { $syntaxBad[] = $f . ' (falta)'; continue; }
    if (substr($f, -4) === '.php') {
        $out = [];
        $rc = 0;
        @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1', $out, $rc);
        if ($rc !== 0) $syntaxBad[] = $f . ' (error de sintaxis)';
    }
}
$checks['Archivos esenciales'] = [$syntaxBad ? 'Problema: ' . implode(', ', $syntaxBad) : 'todos presentes y con sintaxis correcta', !$syntaxBad];
if ($syntaxBad) $bad++;

/* 3) Permisos de escritura (los necesita el actualizador y las subidas) */
$writable = is_writable(__DIR__) && is_writable(__DIR__ . '/uploads');
$checks['Permisos de escritura'] = [$writable ? 'correctos' : 'FALTA permiso de escritura en la carpeta de la app o en uploads/', $writable];

/* 4) Base de datos (si se puede leer config.php) */
$dbTxt = 'No se ha comprobado (sin config.php)';
$dbOk  = true;
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/includes/functions.php';
    try {
        $pdo = db();
        $n = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $dbTxt = 'Conexión correcta · ' . $n . ' tablas';
    } catch (Throwable $e) {
        $dbOk = false;
        $dbTxt = 'Error: ' . $e->getMessage();
    }
    /* Tablas que la app necesita */
    $need = ['users', 'ad_banners'];
    $faltan = [];
    try {
        $rows = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($need as $tb) if (!in_array(t($tb), $rows, true)) $faltan[] = $tb;
    } catch (Throwable $e) { $faltan = ['(no se pudo comprobar)']; }
    if ($faltan) {
        $dbOk = false;
        $dbTxt .= ' · FALTA: ' . implode(', ', $faltan) . ' (créala en phpMyAdmin o deja que la app lo haga al entrar)';
    }
}
$checks['Base de datos'] = [$dbTxt, $dbOk];
if (!$dbOk) $bad++;

/* 5) Salida a Internet hacia GitHub (solo si hay cURL o fopen) */
$netTxt = 'No comprobada';
$netOk  = true;
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.github.com/repos/' . $repo);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => true, CURLOPT_USERAGENT => 'AirsoftSocial-health']);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $netOk = ($code === 200 || $code === 404);
    $netTxt = 'GitHub responde con el código ' . $code . ($code === 404 ? ' (el repositorio es privado: añade GITHUB_TOKEN en config.php)' : '');
} elseif (ini_get('allow_url_fopen')) {
    $netTxt = 'Sin cURL: se usaría allow_url_fopen (más lento)';
}
$checks['Salida a Internet'] = [$netTxt, $netOk];

$rows = '';
foreach ($checks as $k => $v) {
    $rows .= '<tr><th>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</th><td>'
        . ($v[1] ? '✅ ' : '❌ ') . htmlspecialchars((string)$v[0], ENT_QUOTES, 'UTF-8') . '</td></tr>';
}
?><!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico · <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{color-scheme:dark}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#14170f;color:#e8eae2;
  margin:0;padding:28px 18px;line-height:1.55}
.wrap{max-width:720px;margin:0 auto}
h1{color:#c8d64b;font-size:21px;margin:0 0 4px}
.sub{color:#8a9080;font-size:13px;margin:0 0 20px}
.card{background:#1b1f15;border:1px solid #2c3324;border-radius:10px;padding:16px;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #2c3324;vertical-align:top}
th{color:#8a9080;font-weight:600;width:40%}
code{background:#0d1108;border:1px solid #2c3324;border-radius:4px;padding:2px 6px;font-size:12.5px;
  color:#c8d64b;word-break:break-all}
.ok{color:#9fd47a}.ko{color:#e08a6a}
pre{background:#0d1108;border:1px solid #2c3324;border-left:3px solid #c8d64b;border-radius:6px;padding:10px 12px;
  overflow-x:auto;font-size:12.5px;white-space:pre-wrap;word-break:break-all}
a{color:#c8d64b}
</style></head><body><div class="wrap">
<h1>Diagnóstico de la instalación</h1>
<p class="sub">Versión instalada: <strong>v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></strong> · repositorio de actualizaciones: <?= htmlspecialchars($repo, ENT_QUOTES, 'UTF-8') ?></p>

<div class="card"><table><?= $rows ?></table></div>

<?php if ($bad > 0): ?>
<div class="card">
  <h2 class="ko" style="font-size:16px;margin:0 0 8px">Hay <?= $bad ?> problema(s) que explicar</h2>
  <p style="font-size:14px;margin:0 0 10px">Si la web no carga, lo más probable es esto:</p>
  <ul style="font-size:14px;padding-left:20px">
    <li><strong>Archivos con error de sintaxis</strong>: la subida se cortó a mitad. Vuelve a subir los archivos del último release.</li>
    <li><strong>Falta una tabla</strong>: entra en la app como administrador y se creará sola; si tu hosting no lo permite, ejecuta esto en phpMyAdmin:
      <pre>CREATE TABLE `ad_banners` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(160) NOT NULL,
  `description` VARCHAR(400) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(300) NOT NULL,
  `source` VARCHAR(120) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
    </li>
    <li><strong>Permisos</strong>: la carpeta de la app y <code>uploads/</code> deben permitir escritura (755 o 775).</li>
    <li><strong>Error 502</strong>: casi siempre es que el servidor se queda sin memoria o tiempo. Pide a tu hosting que aumente el límite de PHP y que no bloquee las peticiones a api.github.com.</li>
  </ul>
</div>
<?php else: ?>
<div class="card ok"><strong>✅ Todo correcto.</strong> La instalación está sana; si la web no carga, el problema está en el servidor (permisos del hosting, límites de PHP o un error de MySQL), no en la aplicación.</div>
<?php endif; ?>

<p class="sub">Esta página no muestra datos personales ni tus credenciales. Puedes borrarla cuando quieras: la app no la necesita.</p>
</div></body></html>
