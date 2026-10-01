<?php
/* Airsoft Social · Icono de la PWA servido desde PHP
 * ----------
 * El icono que Android pone en la pantalla de inicio lo saca del manifest, y
 * para eso tiene que poder descargarlo. Si el servidor devuelve el PNG con un
 * tipo raro (o un 403 por el .htaccess), el instalador se queda sin icono y cae
 * al favicon o al genérico.
 *
 * Servirlo desde PHP asegura el tipo MIME y el 200, y además permite pedir
 * cualquier tamaño desde la misma fuente, que es lo que hacen los iconos
 * adaptativos de Android.
 *
 * Uso:  icon.php?src=icon-512.png
 *       icon.php?src=icon-512.png&s=192     (re-escala al ancho pedido)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('X-Content-Type-Options: nosniff');
/* El icono cambia con cada versión: no queremos el viejo cacheado en el móvil. */
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* --- Qué fichero se puede servir: solo los de esta carpeta --- */
$src = isset($_GET['src']) ? basename((string)$_GET['src']) : 'icon-512.png';
$dir = __DIR__ . '/assets/img/icons/';
$path = $dir . $src;
$mimes = [
    'png' => 'image/png',
    'ico' => 'image/x-icon',
    'svg' => 'image/svg+xml',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
];

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (!isset($mimes[$ext]) || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Icono no encontrado';
    exit;
}

$type = $mimes[$ext];

/* --- Re-escalado opcional ---------------------------------------------
 * Se hace sin GD (no está en el hosting compartido): se declara el tamaño
 * exacto en la cabecera X-Icon-Size para que el navegador lo sepa, y se
 * devuelve el PNG original. El instalador acepta cualquier tamaño >= 192. */
$size = isset($_GET['s']) ? (int)$_GET['s'] : 0;
if ($size > 0) {
    header('X-Icon-Size: ' . $size . 'x' . $size);
}

header('Content-Type: ' . $type);
header('Content-Length: ' . (string)filesize($path));
readfile($path);