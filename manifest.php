<?php
/* Airsoft Social · Manifest de la PWA servido desde PHP
 * ----------
 * Por qué no basta con el manifest.webmanifest estático: muchos hostings
 * compartidos ignoran la directiva AddType del .htaccess y sirven los ficheros
 * .webmanifest como text/plain. El .htaccess de esta app además lleva
 * X-Content-Type-Options: nosniff, así que Chrome NO intenta adivinar el tipo:
 * si el manifest no llega como application/manifest+json, lo descarta entero.
 *
 * Y si no hay manifest no hay icono ni instalación: el navegador solo ofrece
 * "crear acceso directo", que usa el favicon y no la app.
 *
 * Servirlo desde PHP elimina esa dependencia: las cabeceras las pone PHP y
 * ningún Apache las pisa. El fichero manifest.webmanifest se sigue manteniendo
 * (lo usan Lighthouse y las herramientas de diagnóstico), pero las páginas
 * apuntan a este.
 */

declare(strict_types=1);

/* El service worker tiene que poder servir todo el sitio. */
header('Service-Worker-Allowed: /');
header('X-Content-Type-Options: nosniff');

$file = __DIR__ . '/manifest.webmanifest';
$json = is_file($file) ? (string)file_get_contents($file) : '';

/* Si el JSON del fichero no cuadra, se genera aquí como último recurso: es
 * preferible un manifest con los iconos básicos a ningún manifest. */
if ($json === '' || json_decode($json, true) === null) {
    $json = json_encode([
        'id' => './',
        'name' => 'Airsoft Social',
        'short_name' => 'Airsoft Social',
        'description' => 'Red social para la comunidad airsoft.',
        'lang' => 'es',
        'start_url' => './',
        'scope' => './',
        'display' => 'standalone',
        'background_color' => '#20251a',
        'theme_color' => '#3f4a2f',
        'icons' => [
            ['src' => './assets/img/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => './assets/img/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => './assets/img/icons/icon-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/* Cabeceras: el nombre canónico primero (Chrome lo acepta), y text/plain como
 * respaldo para herramientas antigas que no entienden application/manifest+json. */
header('Content-Type: application/manifest+json; charset=utf-8');
header('Content-Length: ' . strlen($json));
/* El manifest cambia con cada versión: que ni el móvil ni el instalador se
   queden con el icono viejo cacheado. */
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $json;