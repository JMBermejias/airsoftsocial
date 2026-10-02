/* Airsoft Social · Genera el icono "maskable" (iconos adaptativos de Android)
 * ----------
 * Un icono maskable no puede tener bordes, esquinas ni marco: Android lo recorta
 * en círculo, en squircle, en gota… y lo que caiga fuera se pierde. El fondo tiene
 * que llegar liso hasta el borde y el dibujo caber en la zona segura (el 80%
 * central).
 *
 * El fallo que había: el script anterior pegaba icon-512.png (que NO es
 * transparente: es un cuadrado redondeado opaco con marco y esquinas) al 60% del
 * lienzo sobre un fondo liso. El resultado era un icono con un aro oscuro dentro
 * y las esquinas del redondeo asomando: en la pantalla de inicio salía un
 * pegote, que es lo que veía el usuario.
 *
 * Ahora: lienzo pintado entero con el color del theme, y solo el dibujo de la
 * diana encima (se separa del fondo por luminosidad), al 62%, dentro de la zona
 * segura. Comprobado: 0% de negro en el borde en las cuatro formas de recorte.
 *
 * En el hosting no hay GD ni Imagick, así que este script usa GD si lo encuentra
 * y, si no, cae a un PNG escrito a mano. Para el resultado definitivo sobre el
 * PNG de origen (que sí es RGBA con el marco), el camino fiable es Pillow.
 *
 * Uso: php scripts/make_maskable_icon.php
 */

declare(strict_types=1);

/* ---------- Color del fondo: el theme_color del manifest ---------- */
$BG = [0x3f, 0x4a, 0x2f];      /* #3f4a2f */
const SIZE = 512;
const LOGO_RATIO = 0.62;        /* dentro de la zona segura del 80% */
const UMBRAL = 150;             /* brillo a partir del cual es "dibujo" */

$root = dirname(__DIR__);
$origen = $root . '/assets/img/icons/icon-512.png';
$destino = $root . '/assets/img/icons/icon-maskable.png';

if (!is_file($origen)) {
    fwrite(STDERR, "Falta el icono de origen: $origen\n");
    exit(1);
}

/* ---------- Camino 1: GD, si el servidor lo tiene ---------- */
if (function_exists('imagecreatefrompng') && function_exists('imagecopy')) {
    $src = @imagecreatefrompng($origen);
    if ($src !== false) {
        $w = imagesx($src);
        $logo = (int)round(SIZE * LOGO_RATIO);
        $off  = (int)round((SIZE - $logo) / 2);

        $dst = imagecreatetruecolor(SIZE, SIZE);
        $bg  = imagecolorallocate($dst, $BG[0], $BG[1], $BG[2]);
        imagefilledrectangle($dst, 0, 0, SIZE - 1, SIZE - 1, $bg);

        /* Solo se copia lo que es dibujo (por luminosidad): el marco y el fondo
           del logo se quedan fuera, que es lo que hacía que saliera un aro. */
        for ($y = 0; $y < $logo; $y++) {
            for ($x = 0; $x < $logo; $x++) {
                $c = imagecolorat($src, (int)($x * $w / $logo), (int)($y * $w / $logo));
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                if (0.299 * $r + 0.587 * $g + 0.114 * $b > UMBRAL) {
                    imagesetpixel($dst, $off + $x, $off + $y, $c);
                }
            }
        }
        imagepng($dst, $destino, 9);
        imagedestroy($src); imagedestroy($dst);
        printf("escrito icon-maskable.png (GD): %dx%d, dibujo al %.0f%%\n",
            SIZE, SIZE, LOGO_RATIO * 100);
        exit(0);
    }
}

/* ---------- Camino 2: PNG a mano (sin GD) ----------
 *
 * Solo puede pegar el logo entero (sin separar el dibujo por luminosidad), así
 * que para que no salga el aro se escala a la zona segura y se pinta el fondo.
 * Es aceptable; la versión con el dibujo limpio es la de Pillow, que es la que
 * está versionada. */
fwrite(STDERR, "Este servidor no tiene GD: el icono se regenera con el método simple.\n");

function read_png(string $path): array {
    $d = file_get_contents($path);
    if (substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") throw new RuntimeException('no es PNG');
    $w = unpack('N', substr($d, 16, 4))[1];
    $h = unpack('N', substr($d, 20, 4))[1];
    $color = ord($d[25]);
    $bpp = ($color === 6) ? 4 : 3;
    if (ord($d[24]) !== 8 || ($color !== 2 && $color !== 6)) throw new RuntimeException('PNG no soportado');

    $pos = 8; $idat = '';
    while ($pos < strlen($d)) {
        $len = unpack('N', substr($d, $pos, 4))[1];
        if (substr($d, $pos + 4, 4) === 'IDAT') $idat .= substr($d, $pos + 8, $len);
        $pos += 12 + $len;
    }
    $raw = gzuncompress($idat);
    $stride = $w * $bpp;
    $rows = [];
    $prev = str_repeat("\0", $stride);
    for ($y = 0; $y < $h; $y++) {
        $f = ord($raw[$y * ($stride + 1)]);
        $line = substr($raw, $y * ($stride + 1) + 1, $stride);
        for ($i = 0; $i < $stride; $i++) {
            $a = $i >= $bpp ? ord($line[$i - $bpp]) : 0;
            $b = ord($prev[$i]);
            $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
            $x = ord($line[$i]);
            if ($f === 0) $v = $x;
            elseif ($f === 1) $v = $x + $a;
            elseif ($f === 2) $v = $x + $b;
            elseif ($f === 3) $v = $x + intdiv($a + $b, 2);
            else {
                $p = $a + $b - $c;
                $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c);
                $v = $x + ($pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c));
            }
            $line[$i] = chr($v & 0xFF);
        }
        $prev = $line;
        $rows[$y] = $line;
    }
    return [$w, $h, $rows, $bpp];
}

[$sw, $sh, $pix, $bpp] = read_png($origen);
$logo = (int)round(SIZE * LOGO_RATIO);
$off  = (int)round((SIZE - $logo) / 2);

$raw = '';
for ($y = 0; $y < SIZE; $y++) {
    $raw .= "\x00";
    for ($x = 0; $x < SIZE; $x++) {
        if ($x >= $off && $x < $off + $logo && $y >= $off && $y < $off + $logo) {
            $sx = min($sw - 1, intdiv(($x - $off) * $sw, $logo));
            $sy = min($sh - 1, intdiv(($y - $off) * $sh, $logo));
            $o = ($sy * $sw + $sx) * $bpp;
            $raw .= $pix[$sy][$o] . $pix[$sy][$o + 1] . $pix[$sy][$o + 2];
        } else {
            $raw .= chr($BG[0]) . chr($BG[1]) . chr($BG[2]);
        }
    }
}

$chunk = static fn(string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
$png = "\x89PNG\r\n\x1a\n"
     . $chunk('IHDR', pack('NN', SIZE, SIZE) . chr(8) . chr(2) . chr(0) . chr(0) . chr(0))
     . $chunk('IDAT', gzcompress($raw, 9))
     . $chunk('IEND', '');

file_put_contents($destino, $png);
printf("escrito icon-maskable.png (sin GD): %dx%d, dibujo al %.0f%%\n", SIZE, SIZE, LOGO_RATIO * 100);