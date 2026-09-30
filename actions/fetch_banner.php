<?php
/* Rellena los datos del banner de publicidad (origen, título, descripción e imagen)
 * a partir de la URL del anuncio, igual que los productos de la tienda.
 * Devuelve JSON para el administrador. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();
header('Content-Type: application/json; charset=utf-8');

$url = trim($_POST['url'] ?? '');
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    echo json_encode(['ok' => false, 'error' => 'Introduce una URL válida que empiece por http:// o https://.']);
    exit;
}

$meta = fetch_banner_meta($url);
if ($meta['error'] !== '') {
    echo json_encode(['ok' => false, 'error' => $meta['error']]);
    exit;
}

/* Intentamos guardar la imagen en local (más fiable que enlazar el CDN ajeno).
 * Si no se puede, se enlaza la imagen remota directamente. */
$image = null;
if ($meta['image'] !== '') {
    $image = save_remote_image($meta['image'], 'banners') ?: $meta['image'];
}

echo json_encode([
    'ok' => true,
    'source' => $meta['source'],
    'title' => $meta['title'],
    'description' => $meta['description'],
    'image' => $image,
    'warning' => $meta['warning'],
]);
