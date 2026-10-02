<?php
/* Airsoft Social · ¿Qué versión está sirviendo el servidor?
 * ----------
 * Sin esto no hay forma de saber si un cambio ha llegado o no: el actualizador
 * antes decía "aplicada correctamente" aunque no hubiera podido escribir ni un
 * fichero, así que había que fiarse de una respuesta que no era cierta.
 *
 * Esta página lee del servidor (no del repositorio) lo que de verdad está
 * instalado, y lo enseña. Sin sesión: solo muestra números de versión y
 * tamaños de fichero, que no son datos personales.
 *
 * Ábrela en el móvil DESPUÉS de actualizar: si la versión y el favicon son
 * antiguos, el problema es que los ficheros no se están escribiendo, y hay que
 * mirar los permisos en el hosting.
 */

declare(strict_types=1);

/* Sin depender de la base de datos: si la BD no responde, esta página tiene que
   servir igual, porque es justo cuando más falta hace. */
$root = __DIR__;
$version = is_file($root . '/version.txt')
    ? trim((string)file_get_contents($root . '/version.txt'))
    : '(no hay version.txt)';

/* El icono: lo que decide el aspecto del acceso directo. */
$ficheros = [
    'favicon.ico'                 => 'Icono del acceso directo',
    'manifest.webmanifest'        => 'Manifest de la PWA',
    'icon.php'                    => 'Servidor de iconos',
    'manifest.php'                => 'Manifest servido por PHP',
    'assets/img/icons/icon-192.png'     => 'Icono 192',
    'assets/img/icons/icon-512.png'     => 'Icono 512',
    'assets/img/icons/icon-maskable.png'=> 'Icono adaptativo',
    'service-worker.js'           => 'Service worker',
    'assets/css/style.css'        => 'Estilos',
];

/* Tamaño y fecha de cada fichero: si son los mismos que en el paquete nuevo,
   la actualización llegó. */
$info = [];
foreach ($ficheros as $rel => $desc) {
    $p = $root . '/' . $rel;
    if (!is_file($p)) {
        $info[] = [$desc, $rel, 'FALTA', '—'];
        continue;
    }
    clearstatcache(true, $p);
    $info[] = [$desc, $rel, number_format((int)filesize($p)) . ' B',
               date('d/m/Y H:i', (int)filemtime($p))];
}

/* Si el favicon es legible de verdad: un .ico corrupto es lo que hace que el
   acceso directo salga con el icono genérico. */
$favOk = 'desconocido';
$favP = $root . '/favicon.ico';
if (is_file($favP)) {
    $d = (string)file_get_contents($favP, false, null, 0, 6);
    /* 6 bytes: reservado(2) tipo(2) número de imágenes(2). Los .ico válidos
       empiezan por 00 00 01 00. */
    $favOk = (substr($d, 0, 4) === "\x00\x00\x01\x00") ? 'válido' : 'NO válido (cabecera rara)';
    $favBytes = (int)filesize($favP);
}

/* Lo que de verdad importa para el acceso directo: que las URLs que declara el
 * manifest se puedan descargar. Aquí se comprueba icon.php por dentro,
 * pero desde fuera la petición es lo que ve el navegador: si el hosting no
 * acepta la forma de URL que usa el manifest, aquí se ve. */
$urlsIcono = [
    'icon.php?src=icon-192.png',
    'icon.php?src=icon-512.png',
    'icon.php?src=icon-maskable.png',
    'favicon.ico',
];
$pruebaIconos = [];
foreach ($urlsIcono as $u) {
    $p = $root . '/' . $u;
    $pruebaIconos[] = [$u, is_file($p) ? number_format((int)filesize($p)) . ' B' : 'NO EXISTE'];
}

/* Permisos de escritura: si el hosting no deja escribir, la actualización no
   puede funcionar y no hay forma de arreglarlo desde la web. */
$perm = is_writable($root)
    ? 'la carpeta tiene permiso de escritura'
    : 'LA CARPETA NO TIENE PERMISO DE ESCRITURA: las actualizaciones no se pueden instalar';

$e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Versión instalada</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:780px;margin:24px auto;padding:0 16px;
       background:#14170f;color:#e8eae2;line-height:1.6}
  h1{color:#c8d64b;font-size:20px;margin:0 0 4px}
  .muted{color:#8a9080;font-size:13px}
  .box{background:#1c201a;border:1px solid #333;border-radius:10px;padding:14px;margin:12px 0}
  .ok{color:#a8d84f} .ko{color:#e08577} .wa{color:#e8af4f}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{text-align:left;padding:6px 4px;border-bottom:1px solid #2a2f26}
  th{color:#8a9080;font-weight:600;font-size:11px;text-transform:uppercase}
  code{background:#000;padding:1px 6px;border-radius:4px;font-size:13px}
  .big{font-size:32px;font-weight:800;color:#fff}
  .ico{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .ico img{width:48px;height:48px;border-radius:10px;image-rendering:pixelated}
</style>
</head>
<body>

<h1>Qué tiene instalado este servidor</h1>
<p class="muted">Lo que hay ahora mismo en el hosting, no lo que hay en el repositorio.</p>

<div class="box">
  <div class="big">v<?= $e($version) ?></div>
  <div class="muted">Versión en <code>version.txt</code></div>
</div>

<div class="box">
  <div class="ico">
    <?php if (is_file($favP)): ?>
      <img src="favicon.ico" alt="favicon">
    <?php endif; ?>
    <div>
      <strong>favicon.ico</strong>:
      <?php if (is_file($favP)): ?>
        <span class="<?= $favOk === 'válido' ? 'ok' : 'ko' ?>"><?= $e($favOk) ?></span>
        (<?= number_format((int)$favBytes) ?> B).
        Es el icono que sale al crear un acceso directo.
      <?php else: ?>
        <span class="ko">no existe</span>. Sin él, el acceso directo sale con el icono genérico.
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="box">
  <strong class="<?= is_writable($root) ? 'ok' : 'ko' ?>"><?= $e($perm) ?></strong>
</div>

<div class="box">
  <strong>Direcciones de los iconos</strong>
  <p class="muted" style="margin:4px 0 10px">
    Son las URLs que declara el manifest y las páginas. Si alguna no existe, el
    navegador no puede descargar el icono y el acceso directo sale con el icono
    genérico del sistema (aunque el favicon de la barra sí se vea, ese va por otra
    ruta).
  </p>
  <table>
    <thead><tr><th>URL que pide el navegador</th><th>En el servidor</th></tr></thead>
    <tbody>
    <?php foreach ($pruebaIconos as [$u, $estado]): ?>
      <tr>
        <td><code><?= $e($u) ?></code></td>
        <td class="<?= $estado === 'NO EXISTE' ? 'ko' : 'ok' ?>"><?= $e($estado) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="box">
  <table>
    <thead><tr><th>Qué es</th><th>Fichero</th><th>Tamaño</th><th>Actualizado</th></tr></thead>
    <tbody>
    <?php foreach ($info as [$desc, $rel, $tam, $cuando]): ?>
      <tr>
        <td><?= $e($desc) ?></td>
        <td><code><?= $e($rel) ?></code></td>
        <td class="<?= $tam === 'FALTA' ? 'ko' : '' ?>"><?= $e($tam) ?></td>
        <td class="muted"><?= $e($cuando) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin-bottom:0">
    La fecha es la última vez que se escribió el fichero en el servidor. Si tras actualizar
    siguen con una fecha antigua, es que los ficheros no se están escribiendo: eso es un
    problema de permisos del hosting, no de la web.
  </p>
</div>

</body>
</html>