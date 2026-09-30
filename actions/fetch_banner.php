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
    /* La web no declara ninguna imagen de OpenGraph ni de Twitter, así que lo
     * único que hay es su icono de 32x32. En un banner de 728x90 se vería fatal,
     * de modo que no se pone nada. Antes el mensaje daba por hecho que el icono
     * se podía descargar, pero muchos servidores responden a los .ico con un
     * 204 vacío: se intentaba la descarga, fallaba y se encadenaban dos avisos
     * confusos. Aquí se distingue lo que de verdad ha pasado:
     *   - 204/404/403 al pedir el icono -> ni se ha podido mirar su tamaño.
     *   - se ha descargado y era diminuto -> sí sabemos que es un icono. */
    $ico = save_remote_image($meta['image'], 'banners', $url);
    if ($ico['ok']) {
        /* Se ha descargado para poder medirlo, pero no se guarda: un icono de
         * 32x32 no sirve de banner. Se mide y se borra para no dejar basura. */
        $medida = banner_image_size($ico['path']);
        @unlink(dirname(__DIR__) . '/' . $ico['path']);
        $nota = 'Esa web no tiene ninguna imagen para el anuncio: lo único que declara es su icono, '
              . 'de ' . ($medida[0] > 0 ? $medida[0] . ' × ' . $medida[1] . ' px' : 'tamaño desconocido') . ', '
              . 'demasiado pequeño para un banner de 728 × 90. Abre el anuncio en el navegador, '
              . 'guarda la imagen que quieras promocionar en tu móvil u ordenador y súbela con '
              . '«📷 Subir imagen» (lo mejor, una de 728 × 90 px).';
    } else {
        /* Ni siquiera se deja descargar el icono (casi siempre un 204 vacío).
         * No se ha mirado su tamaño, así que no se afirma que sea de 32x32 y no
         * se suelta el código HTTP: para quien administra esto el dato útil es
         * que tiene que subir la imagen a mano, no el motivo del 204. */
        $nota = 'Esa web no tiene ninguna imagen para el anuncio, y su icono pequeño ni siquiera se '
              . 'deja descargar, así que no se ha podido usar. Abre el anuncio en el navegador, guarda '
              . 'la imagen que quieras promocionar en tu móvil u ordenador y súbela con '
              . '«📷 Subir imagen» (lo mejor, una de 728 × 90 px).';
    }
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
