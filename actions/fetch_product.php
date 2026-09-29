<?php
/* Rellena los datos del producto (nombre, precio, imagen, descripción)
 * a partir de la URL del enlace de afiliado. Devuelve JSON para el admin. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();
header('Content-Type: application/json; charset=utf-8');

$url = trim($_POST['url'] ?? '');
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    echo json_encode(['ok' => false, 'error' => 'Introduce una URL válida que empiece por http:// o https://.']);
    exit;
}

$meta = fetch_product_meta($url);
if (isset($meta['error'])) {
    echo json_encode(['ok' => false, 'error' => $meta['error']]);
    exit;
}

/* Intentamos guardar la imagen en local (más fiable que enlazar el CDN de la tienda). */
$image = null;
if ($meta['image'] !== '') {
    $image = save_remote_image($meta['image'], 'products');
}

echo json_encode([
    'ok' => true,
    'name' => $meta['name'],
    'price' => $meta['price'],
    'description' => $meta['description'],
    'image' => $image,
]);