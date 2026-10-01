<?php
/* Airsoft Social · Diagnóstico de la PWA (icono y pantalla de inicio)
 * ----------
 * Por qué existe: que el icono no aparezca en el móvil tiene cuatro causas
 * posibles, todas fuera del alcance de mirar el código:
 *   1. La web va por http:// en vez de https://  → Android no instala nada.
 *   2. El manifest no carga (o carga con un tipo MIME raro).
 *   3. Los iconos que pide el manifest no se descargan.
 *   4. El service worker no está activo (tampoco hay HTTPS, o se bloqueó).
 *
 * Esta página comprueba las cuatro y dice cuál falla. Ábrela en el MÓVIL
 * (no en el ordenador), con la web ya cargada, y me dices qué pone.
 *
 * Avisa de que es una página de diagnóstico y que puede borrarse luego.
 */

require_once __DIR__ . '/includes/functions.php';

$https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
      || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

$swFile   = __DIR__ . '/service-worker.js';
$mfFile   = __DIR__ . '/manifest.webmanifest';
$icoFile  = __DIR__ . '/favicon.ico';
$ico192   = __DIR__ . '/assets/img/icons/icon-192.png';
$ico512   = __DIR__ . '/assets/img/icons/icon-512.png';
$icoMask  = __DIR__ . '/assets/img/icons/icon-maskable.png';

$files = [
    'manifest.php'              => __DIR__ . '/manifest.php',
    'icon.php'                  => __DIR__ . '/icon.php',
    'manifest.webmanifest'      => $mfFile,
    'favicon.ico'               => $icoFile,
    'assets/img/icons/icon-192.png' => $ico192,
    'assets/img/icons/icon-512.png' => $ico512,
    'assets/img/icons/icon-maskable.png' => $icoMask,
    'service-worker.js'         => $swFile,
];

$e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Diagnóstico PWA · <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" sizes="192x192" href="icon.php?src=icon-192.png">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="manifest" href="manifest.php">
<link rel="stylesheet" href="assets/css/style.css?v=<?= e(str_replace('.', '', app_version())) ?>">
<style>
  .chk{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid var(--line);border-radius:10px;margin-bottom:8px;background:var(--bg-soft);font-size:14px}
  .chk b{flex:0 0 auto;font-size:18px;line-height:1.2}
  .ok b{color:var(--lime)} .ko b{color:var(--danger)} .wa b{color:var(--accent)}
  .chk small{display:block;color:var(--muted);font-size:12px;margin-top:2px}
  code{background:#000;padding:1px 5px;border-radius:4px;font-size:12px}
  #live{margin-top:16px;padding:14px;border:2px solid var(--accent);border-radius:10px}
  #live b{display:block;margin-bottom:6px}
  .live-line{font-size:13px;padding:3px 0;font-family:ui-monospace,monospace;word-break:break-all}
</style>
</head>
<body>
<div style="max-width:820px;margin:0 auto;padding:16px">

  <h1 style="font-size:20px;color:#fff">Diagnóstico del icono y la pantalla de inicio</h1>
  <p class="muted">Abre esta página <strong>en el móvil</strong>, con la web ya cargada, y mira qué pone en rojo.</p>

  <h2 style="color:var(--lime);font-size:15px;margin-top:20px">Lo que se comprueba desde el servidor</h2>

  <?php
  /* 1. HTTPS */
  $ok1 = $https;
  $msg1 = $https
      ? 'La web va por <code>https://</code>. Esto es obligatorio: Android no crea el icono sin HTTPS.'
      : 'La web va por <code>http://</code>. <strong>Con esto el icono no va a salir</strong>, por mucho que el código esté bien. Hay que activar el certificado SSL en el panel del hosting.';

  /* 2. Ficheros */
  $faltan = [];
  foreach ($files as $rel => $f) {
      if (!is_file($f)) $faltan[] = $rel;
  }
  $ok2 = count($faltan) === 0;
  $msg2 = $ok2
      ? 'Están los ' . count($files) . ' ficheros (manifest, favicon, iconos y service worker).'
      : 'Faltan en el servidor: <strong>' . implode(', ', array_map($e, $faltan)) . '</strong>.';

  /* 3. Manifest legible */
  $mfIcons = [];
  $mfErr = '';
  if ($ok2 || is_file($mfFile)) {
      $raw = @file_get_contents($mfFile);
      $j = json_decode((string)$raw, true);
      if (!is_array($j)) {
          $mfErr = json_last_error_msg();
      } else {
          foreach (($j['icons'] ?? []) as $i) {
              $src = (string)($i['src'] ?? '');
              /* Los iconos se sirven por icon.php/<fichero> (o icon.php?src=).
                 El fichero real vive en assets/img/icons/, que es donde
                 icon.php lo busca. */
              $rel = preg_replace('#^\./#', '', $src);
              $name = '';
              if (str_contains($rel, 'icon.php/')) {
                  $name = basename((string)explode('icon.php/', $rel, 2)[1]);
              } elseif (str_contains($rel, '?')) {
                  parse_str((string)explode('?', $rel, 2)[1], $q);
                  if (!empty($q['src'])) $name = basename((string)$q['src']);
              }
              $file = $name !== '' ? 'assets/img/icons/' . $name : $rel;
              $mfIcons[] = [
                  'src'    => $src,
                  'sizes'  => (string)($i['sizes'] ?? '?'),
                  'purpose'=> (string)($i['purpose'] ?? '?'),
                  'existe' => is_file(__DIR__ . '/' . $file),
              ];
          }
      }
  }
  $ok3 = ($mfErr === '') && count($mfIcons) >= 2 && !in_array(false, array_column($mfIcons, 'existe'), true);
  $msg3 = $ok3
      ? 'El manifest se lee y declara ' . count($mfIcons) . ' iconos, y todos existen en disco.'
      : ($mfErr !== '' ? 'El manifest no se puede leer como JSON: ' . $e($mfErr) : 'El manifest declara pocos iconos o alguno no está en disco.');

  /* 4. Tamaños que Android exige */
  $sizes = array_map(fn($i) => $i['sizes'], $mfIcons);
  $ok4 = in_array('192x192', $sizes, true) && in_array('512x512', $sizes, true);
  $msg4 = $ok4
      ? 'Declara los tamaños 192 y 512, que son los que Android exige para poder instalar.'
      : 'Faltan los tamaños 192x192 o 512x512 en el manifest, y Android los necesita para instalar.';
  ?>

  <div class="chk <?= $ok1 ? 'ok' : 'ko' ?>">
    <b><?= $ok1 ? '✔' : '✘' ?></b>
    <div><strong>HTTPS</strong><small><?= $msg1 ?></small></div>
  </div>

  <div class="chk <?= $ok2 ? 'ok' : 'ko' ?>">
    <b><?= $ok2 ? '✔' : '✘' ?></b>
    <div><strong>Ficheros en el servidor</strong><small><?= $msg2 ?></small></div>
  </div>

  <div class="chk <?= $ok3 ? 'ok' : 'ko' ?>">
    <b><?= $ok3 ? '✔' : '✘' ?></b>
    <div><strong>Manifest</strong><small><?= $msg3 ?></small></div>
  </div>

  <div class="chk <?= $ok4 ? 'ok' : 'ko' ?>">
    <b><?= $ok4 ? '✔' : '✘' ?></b>
    <div><strong>Tamaños de los iconos</strong><small><?= $msg4 ?></small></div>
  </div>

  <?php if ($mfIcons): ?>
  <h2 style="color:var(--lime);font-size:15px;margin-top:20px">Iconos declarados</h2>
  <div class="card" style="padding:10px">
    <?php foreach ($mfIcons as $i): ?>
      <div class="live-line">
        <?= $i['existe'] ? '✔' : '✘' ?> <?= e($i['src']) ?>
        <span style="color:var(--muted)">— <?= e($i['sizes']) ?>, <?= e($i['purpose']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 style="color:var(--lime);font-size:15px;margin-top:20px">Lo que ve el navegador (se rellena solo)</h2>
  <div class="card" id="live">
    <b>Resultado en este navegador</b>
    <div class="live-line" id="r-sw">service worker: comprobando…</div>
    <div class="live-line" id="r-https">HTTPS: comprobando…</div>
    <div class="live-line" id="r-sw">service worker: comprobando…</div>
    <div class="live-line" id="r-mf">manifest: comprobando…</div>
    <div class="live-line" id="r-mime">tipo MIME del manifest: comprobando…</div>
    <div class="live-line" id="r-icons">iconos: comprobando…</div>
    <div class="live-line" id="r-verdict">veredicto: comprobando…</div>
  </div>

  <p class="muted" style="font-size:12px;margin-top:8px">
    <strong style="color:var(--text)">Lo importante:</strong> la línea del tipo MIME tiene que
    decir <code>application/manifest+json</code>. Si dice otra cosa (por ejemplo
    <code>text/plain</code>), el navegador descarta el manifest entero: por eso no hay ni icono
    ni opción de instalar, solo «crear acceso directo».
  </p>

  <h2 style="color:var(--lime);font-size:15px;margin-top:20px">Cómo se pone el icono</h2>
  <div class="card">
    <p class="muted" style="margin-top:0">
      <strong style="color:var(--text)">Android (Chrome):</strong> menú ⋮ → «Instalar aplicación» o «Añadir a pantalla de inicio». Si no aparece la primera opción, es porque falta HTTPS o el service worker.<br><br>
      <strong style="color:var(--text)">iPhone (Safari):</strong> Compartir ⬆️ → «Añadir a pantalla de inicio». En iOS el icono lo pone Safari, no la web.
    </p>
  </div>

  <p class="muted" style="margin-top:20px;font-size:12px">
    Diagnóstico interno. No muestra datos de la base de datos y no lo necesita para nada más.
    Puedes borrar este fichero cuando lo hayas usado.
  </p>
</div>

<script>
(function () {
  var out = function (id, txt, good) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = (good === true ? '✔ ' : good === false ? '✘ ' : '') + txt;
    el.style.color = good === true ? '#a8d84f' : good === false ? '#e08577' : 'var(--muted)';
  };

  var isHttps = location.protocol === 'https:'
            || location.hostname === 'localhost'
            || location.hostname === '127.0.0.1';

  out('r-https', 'protocolo = ' + location.protocol, isHttps);

  /* Service worker */
  if (!('serviceWorker' in navigator)) {
    out('r-sw', 'este navegador no soporta service workers', false);
  } else {
    navigator.serviceWorker.getRegistration().then(function (reg) {
      if (!reg) {
        out('r-sw', 'no hay service worker registrado', false);
      } else if (navigator.serviceWorker.controller) {
        out('r-sw', 'activo y controlando la página', true);
      } else {
        out('r-sw', 'registrado pero todavía no activo (recarga la página)', null);
      }
    }).catch(function (e) { out('r-sw', 'error: ' + e, false); });
  }

  /* Manifest: hay que pedirlo a mano para ver si carga de verdad */
  var link = document.querySelector('link[rel="manifest"]');
  if (!link) {
    out('r-mf', 'no hay <link rel="manifest"> en la página', false);
  } else {
    fetch(link.href, { cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        out('r-mf', 'carga bien (HTTP ' + r.status + ')', true);
        /* El tipo MIME es LA causa típica de que no haya icono: si no es
           application/manifest+json, el navegador descarta el manifest entero. */
        var ct = (r.headers.get('content-type') || '').split(';')[0].trim();
        var mimeOk = (ct === 'application/manifest+json');
        out('r-mime', ct || '(sin tipo)', mimeOk);
        return r.json();
      })
      .then(function (j) {
        var icons = (j && j.icons) || [];
        out('r-mf', 'iconos declarados: ' + icons.length, icons.length >= 2);
        /* Que el navegador se los baje de verdad */
        if (!icons.length) { out('r-icons', 'el manifest no trae iconos', false); return; }
        var pend = icons.length;
        var malos = 0;
        icons.forEach(function (ic) {
          new Image().onload = function () {
            if (--pend === 0) {
              out('r-icons', malos ? (malos + ' icono(s) no se descargan') : 'todos los iconos se descargan', malos === 0);
              veredicto();
            }
          };
          new Image().onerror = function () { malos++; if (--pend === 0) {
            out('r-icons', malos + ' icono(s) NO se descargan', false); veredicto(); } };
          new Image().src = ic.src;
        });
      })
      .catch(function (e) { out('r-mf', 'no carga: ' + e.message, false); veredicto(); });
  }

  function veredicto() {
    var https = isHttps;
    var swOk = !!navigator.serviceWorker.controller;
    var green = 'rgb(168, 216, 79)';
    var mfEl = document.getElementById('r-mf');
    var mfOk = mfEl && mfEl.style.color === green;
    var mimeEl = document.getElementById('r-mime');
    var mimeOk = mimeEl && mimeEl.style.color === green;
    var icEl = document.getElementById('r-icons');
    var icOk = icEl && icEl.style.color === green;
    var v;
    if (!https)       v = 'SIN HTTPS: el icono no saldrá hasta que actives el SSL en el hosting.';
    else if (!swOk)   v = 'HTTPS correcto pero el service worker no está activo: recarga la página un par de veces.';
    else if (!mimeOk) v = 'ESTE ES EL PROBLEMA: el manifest no llega con el tipo correcto, así que el navegador lo descarta entero. Mira la línea de arriba.';
    else if (!mfOk)   v = 'El manifest carga pero no se puede leer como JSON. Revisa manifest.php.';
    else if (!icOk)   v = 'Manifest correcto pero algún icono no se descarga. Mira la lista de arriba.';
    else              v = 'Todo correcto. Si el icono sigue sin salir, borra los datos del sitio desde los ajustes del navegador y reinstala.';
    out('r-verdict', v, https && swOk && mimeOk && mfOk && icOk);
  }
  setTimeout(veredicto, 2500);
})();
</script>
</body>
</html>