<?php
/* Crea icon-maskable.png: icono de Android con la zona segura respetada.
 *
 * Un icono "maskable" puede ser recortado por el sistema hasta un 20% por cada
 * lado (el launcher lo convierte en círculo, cuadrado redondeado, etc.). Por eso
 * el contenido importante no puede llegar hasta el borde: aquí se deja un
 * margen del 20% y el logo ocupa solo el 60% central.
 *
 * Sin GD, así que se escribe el PNG a mano (solo hace falta zlib, que viene en
 * PHP siempre). */
@chdir(dirname(__DIR__));

function chunk(string $type, string $data): string {
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

/** Convierte un PNG RGB de 8 bits ya leído en píxeles RGB planos. */
function read_png(string $file): array {
    $d = file_get_contents($file);
    if (substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") return [0, 0, ''];
    $w = unpack('N', substr($d, 16, 4))[1];
    $h = unpack('N', substr($d, 20, 4))[1];
    $ch = ord(substr($d, 25, 1));          /* canales: 2=rgb, 6=rgba */
    $idat = '';
    $off = 8;
    while ($off < strlen($d)) {
        $len = unpack('N', substr($d, $off, 4))[1];
        $type = substr($d, $off + 4, 4);
        if ($type === 'IDAT') $idat .= substr($d, $off + 8, $len);
        $off += 12 + $len;
    }
    $raw = gzuncompress($idat);
    $bpp = $ch === 6 ? 4 : 3;
    $out = '';
    $prev = str_repeat("\x00", $w * $bpp);
    $pos = 0;
    for ($y = 0; $y < $h; $y++) {
        $f = ord($raw[$pos++]);
        $line = substr($raw, $pos, $w * $bpp);
        $pos += $w * $bpp;
        $cur = '';
        for ($i = 0; $i < $w * $bpp; $i++) {
            $x = ord($line[$i]);
            $a = $i >= $bpp ? ord($cur[$i - $bpp]) : 0;
            $b = ord($prev[$i]);
            $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
            if ($f === 0)      $v = $x;
            elseif ($f === 1)  $v = $x + $a;
            elseif ($f === 2)  $v = $x + $b;
            elseif ($f === 3)  $v = $x + intdiv($a + $b, 2);
            else {
                $p = $a + $b - $c;
                $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c);
                $pr = ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                $v = $x + $pr;
            }
            $cur .= chr($v & 0xFF);
        }
        $out .= $cur;
        $prev = $cur;
    }
    return [$w, $h, $out, $bpp];
}

list($sw, $sh, $pix, $bpp) = read_png('assets/img/icons/icon-512.png');
if ($sw === 0) { fwrite(STDERR, "No se pudo leer el PNG de origen\n"); exit(1); }
printf("origen leído: %dx%d (%d canales)\n", $sw, $sh, $bpp);

/* El logo va centrado y al 60% (zona segura = 80%, y se deja aire de más). */
$size = 512;
$logo = (int)round($size * 0.60);
$ox = (int)round(($size - $logo) / 2);
$oy = $ox;
$bg = [0x3f, 0x4a, 0x2f];   /* theme_color del manifest */

$raw = '';
for ($y = 0; $y < $size; $y++) {
    $raw .= "\x00";          /* filtro None */
    for ($x = 0; $x < $size; $x++) {
        if ($x >= $ox && $x < $ox + $logo && $y >= $oy && $y < $oy + $logo) {
            /* Escala el logo original a $logo x $logo. */
            $sx = intdiv(($x - $ox) * $sw, $logo);
            $sy = intdiv(($y - $oy) * $sh, $logo);
            $o  = ($sy * $sw + $sx) * $bpp;
            if ($bpp === 4 && ord($pix[$o + 3]) < 128) {
                /* El logo es transparente aquí: se ve el fondo. */
                $raw .= chr($bg[0]) . chr($bg[1]) . chr($bg[2]);
            } else {
                $raw .= $pix[$o] . $pix[$o + 1] . $pix[$o + 2];
            }
        } else {
            $raw .= chr($bg[0]) . chr($bg[1]) . chr($bg[2]);
        }
    }
}

$png = "\x89PNG\r\n\x1a\n"
     . chunk('IHDR', pack('NN', $size, $size) . chr(8) . chr(2) . chr(0) . chr(0) . chr(0))
     . chunk('IDAT', gzcompress($raw, 9))
     . chunk('IEND', '');

file_put_contents('assets/img/icons/icon-maskable.png', $png);
printf("escrito assets/img/icons/icon-maskable.png (%d bytes)\n", filesize('assets/img/icons/icon-maskable.png'));
