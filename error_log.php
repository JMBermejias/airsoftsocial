<?php
/* Airsoft Social · Ver el registro de errores
 * ----------
 * Un "502 Bad Gateway" no lleva texto: es Apache o PHP-FPM diciendo que el PHP
 * no pudo terminar. Este archivo guarda lo que pasó en
 * uploads/_system/error.log, que sí se puede abrir desde el navegador.
 *
 * Solo para quien administers la web: pide la contraseña del admin o, si no la
 * tienes, borra o renombra este fichero (no contiene datos de los usuarios,
 * solo mensajes de error técnicos).
 */

declare(strict_types=1);

$__config = __DIR__ . '/config.php';
if (is_file($__config)) require_once $__config;

/* Para verlo hay que ser admin de la web o conocer la contraseña de diagnóstico.
   Nunca se muestra abierto: el log contiene rutas del servidor y mensajes de
   SQL, que no debe ver cualquiera que encuentre la dirección. */
$diagPass = (string)(defined('DIAG_PASSWORD') ? DIAG_PASSWORD : '');
$ok = false;

/* 1) Admin de la web. */
try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (function_exists('is_logged') && is_logged() && is_admin()) $ok = true;
} catch (Throwable $e) {
    /* Sin base de datos no se puede comprobar la sesión: sigue el paso 2. */
}

/* 2) Contraseña de diagnóstico, si está definida en config.php. */
if (!$ok && $diagPass !== '') {
    $given = (string)($_GET['k'] ?? ($_POST['k'] ?? ''));
    if ($given !== '' && hash_equals($diagPass, $given)) $ok = true;
}

$e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

if (!$ok) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Acceso restringido</title></head>'
       . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;background:#14170f;color:#e8eae2">'
       . '<h1 style="color:#c8d64b;font-size:20px;margin:0 0 12px">Acceso restringido</h1>';

    if ($diagPass !== '') {
        /* Hay contraseña puesta: se pide. */
        echo '<p style="line-height:1.6">Escribe la contraseña de diagnóstico.</p>'
           . '<form method="get" style="margin-top:12px">'
           . '<input type="password" name="k" autofocus '
           . 'style="padding:10px;width:260px;border-radius:8px;border:1px solid #444;background:#1c201a;color:#fff">'
           . ' <button class="btn btn-primary" style="padding:10px 16px;border-radius:8px;border:0;cursor:pointer">Entrar</button>'
           . '</form>';
    } else {
        /* No hay contraseña: se explica cómo ponerla. Sin esto la página no
           podría abrirse nunca, porque comprobar el admin necesita la web
           funcionando y justo es lo que está fallando. */
        echo '<p style="line-height:1.6">Para ver el registro hace falta una de estas dos cosas:</p>'
           . '<ul style="line-height:1.7">'
           . '<li><strong>Entrar con una cuenta de administrador</strong> en la web. '
           . 'Si la web no carga, esto no te sirve.</li>'
           . '<li>O añadir una contraseña en <code>config.php</code>:</li>'
           . '</ul>'
           . '<pre style="background:#1c201a;padding:12px;border-radius:8px;overflow-x:auto">'
           . "define('DIAG_PASSWORD', 'la-que-quieras');"
           . '</pre>'
           . '<p class="muted" style="color:#8a9080;font-size:13px;margin-top:12px">'
           . ' Después vuelve a abrir esta página con <code>?k=tu-contraseña</code>. '
           . 'Esta contraseña es solo para diagnóstico: bórrala cuando acabes.</p>';
    }

    echo '</body></html>';
    exit;
}

$logFile = __DIR__ . '/uploads/_system/error.log';

/* Borrar el registro. Con la contraseña puesta hay que reenviarla, porque el
   enlace tiene que seguir pasando la comprobación de acceso. */
$selfUrl = 'error_log.php' . ($diagPass !== '' ? '?k=' . rawurlencode($diagPass) : '');
if (isset($_GET['borrar']) && $ok) {
    @unlink($logFile);
    header('Location: ' . $selfUrl);
    exit;
}

$lines  = is_file($logFile) ? @file($logFile, FILE_IGNORE_NEW_LINES) : false;
$exists = is_file($logFile);
$size   = $exists ? (int)@filesize($logFile) : 0;

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Registro de errores · <?= $e(defined('APP_NAME') ? APP_NAME : 'Airsoft Social') ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= $e(trim(@file_get_contents(__DIR__ . '/version.txt') ?: '1')) ?>">
</head>
<body>
<div style="max-width:1000px;margin:0 auto;padding:16px">
  <h1 style="color:#fff;font-size:20px;margin:0 0 6px">Registro de errores</h1>
  <p class="muted" style="margin:0 0 14px">
    <?php if ($exists): ?>
      <?= count($lines) ?> línea(s) · <?= number_format($size / 1024, 1) ?> KB
    <?php else: ?>
      Todavía no hay nada escrito.
    <?php endif; ?>
  </p>

  <?php if (!$exists): ?>
    <div class="card">
      <p style="margin:0">No hay archivo de registro todavía. Eso significa que PHP no ha fallado de
      forma grave desde que se creó, o que el archivo no se puede escribir.</p>
      <p class="muted" style="margin:8px 0 0">
        Si el problema es un 502 y aquí no hay nada, el fallo está en Apache o en PHP-FPM antes de
        que entre el PHP: habría que mirar el <strong>log de errores del hosting</strong>.
      </p>
    </div>
  <?php else: ?>
    <?php /* El separador: & si selfUrl ya trae ?k=, & si no. */ ?>
    <p><a href="<?= $e($selfUrl) ?><?= strpos($selfUrl, '?') === false ? '?' : '&' ?>borrar=1"
          onclick="return confirm('¿Borrar el registro?')"
          style="color:#e08577;font-size:13px">Borrar el registro</a></p>
    <div class="card" style="padding:12px">
      <pre style="margin:0;white-space:pre-wrap;word-break:break-word;font-size:12px;color:#e8eae2"><?= $e(implode("\n", array_slice($lines, -300))) ?></pre>
    </div>
  <?php endif; ?>

  <p class="muted" style="margin-top:16px;font-size:12px">
    Solo mensajes técnicos de PHP. No contiene datos de usuarios ni contraseñas.
    Puedes borrar este fichero cuando termines.
  </p>
</div>
</body>
</html>