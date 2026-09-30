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

/* Intentamos descargar la imagen a uploads/banners/ (más fiable que enlazar el
 * CDN ajeno). Si algo falla, se explica exactamente por qué y qué hacer. */
$image  = null;
$nota   = '';
$kind   = (string)($meta['image_kind'] ?? '');

if ($kind === 'favicon') {
    /* Es el icono de la web (32x32). En un banner de 728x90 se ve fatal, así
     * que no se pone nada y se dice claramente que suba una imagen. */
    $nota = 'Esa web no tiene ninguna imagen para el anuncio (solo su icono, de 32 × 32 px), '
          . 'así que no se ha puesto ninguna. Abre el anuncio, guarda la imagen que quieras '
          . 'promocionar en tu móvil o ordenador y súbela con «📷 Subir imagen» '
          . '(lo mejor, una de 728 × 90 px).';
} elseif ($meta['image'] !== '') {
    /* 120x60 es el mínimo razonable: por debajo es un icono, y en un banner de
     * 728x90 se vería fatal. save_remote_image() lo rechaza ANTES de escribir
     * el fichero, así que no queda ningún icono suelto en uploads/banners/. */
    $dl = save_remote_image($meta['image'], 'banners', $url, ['min_w' => 120, 'min_h' => 60]);
    if ($dl['ok']) {
        $image = $dl['path'];
    } elseif (!empty($dl['too_small'])) {
        /* Un icono enlazado tampoco serviría de nada, así que no se pone nada. */
        $nota = $dl['error'] . ' La web no tiene una imagen grande para el banner, así que no se ha '
              . 'puesto ninguna. Sube tú una de 728 × 90 con «📷 Subir imagen».';
    } else {
        /* No se ha podido guardar: se enlaza la original, que muchas veces sí
         * se ve en el navegador, y se explica el motivo REAL del fallo. */
        $image = $meta['image'];
        $nota  = $dl['error'] . ' Se ha enlazado la imagen original: si tampoco se ve, súbela tú con '
               . '«📷 Subir imagen» o pega su dirección en «URL de la imagen».';
    }
} else {
    $nota = 'No se ha encontrado ninguna imagen en esa web. Sube tú una de 728 × 90 con «📷 Subir imagen».';
}

/* Se mide ahora para saber cómo mostrarla (apaisada a 728x90 o entera al lado). */
$wide = false;
if (!empty($image)) {
    [$iw, $ih] = banner_image_size((string)$image);
    $wide = $iw > 0 && $ih > 0 && ($iw / $ih) >= 7.5;
}

echo json_encode([
    'ok' => true,
    'source' => $meta['source'],
    'title' => $meta['title'],
    'description' => $meta['description'],
    'image' => $image,
    'wide' => $wide,
    'warning' => trim(($meta['warning'] !== '' ? $meta['warning'] . ' ' : '') . $nota),
]);
