<?php
/* Airsoft Social · Genera favicon.ico
 * ----------
 * Por qué: muchos navegadores y el instalador de Android piden /favicon.ico
 * antes de mirar el manifest. Si el fichero no existe, se acabó: sale un icono
 * genérico o directamente nada. Se generan tres tamaños dentro de un único .ico
 * (16, 32 y 48 px), que es lo que aceptan todos.
 *
 * Se leen los píxeles de icon-192.png (ya existe y es válido) y se reescalan.
 * Sin GD en el servidor, así que se hace a mano, igual que el icono maskable.
 *
 * Uso: php scripts/make_favicon.php
 */

declare(strict_types=1);

/* ---------- Lectura de PNG ---------- */
function read_png(string $path): array {
    $d = file_get_contents($path);
    if (substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new RuntimeException("No es un PNG: $path");
    }
    $w = unpack('N', substr($d, 16, 4))[1];
    $h = unpack('N', substr($d, 20, 4))[1];
    $depth = ord($d[24]);
    $color = ord($d[25]);
    if ($depth !== 8 || ($color !== 2 && $color !== 6)) {
        throw new RuntimeException("PNG no soportado (profundidad $depth, color $color)");
    }
    $channels = ($color === 6) ? 4 : 3;

    $pos = 8;
    $idat = '';
    while ($pos < strlen($d)) {
        $len  = unpack('N', substr($d, $pos, 4))[1];
        $type = substr($d, $pos + 4, 4);
        if ($type === 'IDAT') $idat .= substr($d, $pos + 8, $len);
        $pos += 12 + $len;
    }
    $raw = gzuncompress($idat);

    /* Deshacer los filtros de línea, uno a uno. */
    $bpp    = $channels;
    $stride = $w * $channels;
    $out    = [];
    $prev   = array_fill(0, $stride, "\0");

    for ($y = 0; $y < $h; $y++) {
        $filter = ord($raw[$y * ($stride + 1)]);
        $line   = substr($raw, $y * ($stride + 1) + 1, $stride);
        for ($i = 0; $i < $stride; $i++) {
            $a = $i >= $bpp ? ord($line[$i - $bpp]) : 0;
            $b = ord($prev[$i]);
            $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
            $x = ord($line[$i]);
            if ($filter === 0) $v = $x;
            elseif ($filter === 1) $v = $x + $a;
            elseif ($filter === 2) $v = $x + $b;
            elseif ($filter === 3) $v = $x + intdiv($a + $b, 2);
            else {
                $p = $a + $b - $c;
                $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c);
                $v = $x + ($pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c));
            }
            $line[$i] = chr($v & 0xFF);
        }
        $prev = $line;
        $out[$y] = $line;
    }
    return [$w, $h, $out, $channels];
}

/* ---------- Escritura de ICO ----------
 * Un .ico es una cabecera con el catálogo de imágenes y, para cada una, los
 * píxeles en BMP sin cabecera: filas de abajo arriba y canal alfa en BYTE. */
function build_ico(array $images): string {
    $count = count($images);

    /* offsets = 6 (cabecera) + 16 × n (catálogo) */
    $offset = 6 + $count * 16;
    $entries = '';
    $data    = '';

    foreach ($images as $img) {
        $size = $img['size'];
        $mask = str_repeat("\x00", (int)(($size + 31) / 32) * 4 * $size);  /* AND, toda cero */
        $body = $img['pixels'];

        $entries .= chr($size >= 256 ? 0 : $size)
                  . chr($size >= 256 ? 0 : $size)
                  . chr(0)      /* paleta: 0 = truecolor */
                  . chr(0)      /* reservado */
                  . pack('v', 1) /* planos */
                  . pack('v', 32)/* bits por píxel */
                  . pack('V', strlen($body) + strlen($mask))
                  . pack('V', $offset);

        $data .= $body . $mask;
        $offset += strlen($body) + strlen($mask);
    }

    return pack('v', 0) . pack('v', 1) . pack('v', $count) . $entries . $data;
}

/* ---------- Programa ---------- */
[$sw, $sh, $src, $srcChannels] = read_png('assets/img/icons/icon-192.png');
printf("origen leído: %dx%d (%d canales)\n", $sw, $sh, $srcChannels);

$images = [];
foreach ([16, 32, 48] as $size) {
    /* BMP: cada fila va de abajo arriba, en BGR y con un byte alpha (0 = opaco). */
    $body = '';
    for ($y = $size - 1; $y >= 0; $y--) {
        $row = '';
        for ($x = 0; $x < $size; $x++) {
            /* Promediado en 2x2: al reducir tanto, elegir un solo píxel deja
             * los bordes del logo sucios. */
            $r = $g = $b = 0;
            $n = 0;
            for ($dy = 0; $dy < 2; $dy++) {
                for ($dx = 0; $dx < 2; $dx++) {
                    $sx = min($sw - 1, intdiv(($x * 2 + $dx) * $sw, $size * 2));
                    $sy = min($sh - 1, intdiv(($y * 2 + $dy) * $sh, $size * 2));
                    $o  = $sx * $srcChannels;
                    $r  += ord($src[$sy][$o]);
                    $g  += ord($src[$sy][$o + 1]);
                    $b  += ord($src[$sy][$o + 2]);
                    $n++;
                }
            }
            $row .= chr(intdiv($b, $n)) . chr(intdiv($g, $n)) . chr(intdiv($r, $n)) . "\x00";
        }
        $body .= $row;
    }
    $images[] = ['size' => $size, 'pixels' => $body];
}

$ico = build_ico($images);
file_put_contents('favicon.ico', $ico);
printf("escrito favicon.ico (%d bytes, tamaños 16/32/48)\n", strlen($ico));