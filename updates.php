<?php
$page_title = 'Actualización de la app';
$page_subtitle = 'Comprueba y aplica nuevas versiones de Airsoft Social';
require_once __DIR__ . '/includes/functions.php';
require_admin();

$force = (int)($_GET['force'] ?? 0) === 1;
$rel = latest_release($force);
$current = app_version();
$upd = update_available();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$logf = update_state_dir() . '/log.json';
$log = is_file($logf) ? json_decode((string)file_get_contents($logf), true) : [];
if (!is_array($log)) $log = [];

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="card">
  <div class="update-row">
    <div>
      <h3>Versión instalada</h3>
      <p class="big-version"><strong>v<?= e($current) ?></strong></p>
      <p class="muted">Comprobación automática cada <?= update_check_hours() ?> h contra
        <a href="https://github.com/<?= e(update_github_repo()) ?>/releases" target="_blank" rel="noopener">GitHub</a>.</p>
      <p class="muted" style="margin:6px 0 0">
        ¿La versión es la que esperabas?
        <a href="que_version.php" target="_blank" rel="noopener">Ver qué hay instalado en el servidor</a>
      </p>
    </div>
    <div>
      <a class="btn btn-ghost btn-small" href="updates.php?force=1">🔄 Comprobar ahora</a>
    </div>
  </div>
</div>

<?php if ($upd): ?>
  <div class="card update-available">
    <h3>⬆️ Hay una nueva versión: <span class="lime">v<?= e($upd['latest']) ?></span></h3>
    <p class="muted">Publicada el <?= e(date('d/m/Y H:i', strtotime($rel['published_at'] ?: 'now'))) ?>
      · <a href="https://github.com/<?= e(update_github_repo()) ?>/releases/tag/<?= e($upd['tag']) ?>" target="_blank" rel="noopener">ver release en GitHub</a></p>
    <?php if ($upd['body'] !== ''): ?>
      <div class="changelog"><?= nl2br(e($upd['body'])) ?></div>
    <?php endif; ?>
    <form method="post" action="actions/update.php" style="margin-top:14px"
          onsubmit="return confirm('¿Aplicar la actualización a v<?= e($upd['latest']) ?>?\n\nconfig.php y uploads/ se conservan intactos.')">
      <?= csrf_field() ?>
      <button class="btn btn-primary" type="submit">⬇️ Actualizar ahora a v<?= e($upd['latest']) ?></button>
    </form>
  </div>
<?php elseif (empty($rel['ok'])): ?>
  <div class="alert error"><?= e($rel['error'] ?? 'No se pudo comprobar actualizaciones.') ?></div>
  <?php $diag = update_diagnostics(true); ?>
  <div class="card">
    <h3>Diagnóstico del servidor</h3>
    <table class="table">
      <tr><td>Versión instalada</td><td>v<?= e($diag['instalada']) ?></td></tr>
      <tr><td>Repositorio de las actualizaciones</td><td><?= e($diag['repo']) ?></td></tr>
      <tr><td>Token de GitHub en config.php</td><td><?= $diag['token'] ? '✅ configurado' : '❌ no hay' ?></td></tr>
      <tr><td>Extensión cURL</td><td><?= $diag['curl'] ? '✅ disponible' : '❌ no disponible' ?></td></tr>
      <tr><td>allow_url_fopen</td><td><?= $diag['allow_url_fopen'] ? '✅ activado' : '⚠️ desactivado' ?></td></tr>
      <tr><td>Conexión con GitHub</td><td><?= e($diag['conexion']) ?></td></tr>
    </table>
    <?php if (!$diag['token']): ?>
      <h4>Cómo solucionarlo</h4>
      <p class="muted">Si el repositorio de arriba es <strong>privado</strong>, GitHub no enseña sus
        releases a esta instalación. Añade esta línea en tu <code>config.php</code>:</p>
      <pre class="code-block">define('GITHUB_TOKEN', 'ghp_...');</pre>
      <p class="muted">El token solo necesita permiso de <strong>lectura de contenido</strong> del repo
        (GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens).
        Si prefieres no usar token, deja el repositorio de las actualizaciones en <strong>público</strong>.</p>
    <?php elseif (!$diag['curl']): ?>
      <p class="muted">Pide a tu hosting que active la extensión <strong>cURL</strong> de PHP (o que
        active <code>allow_url_fopen</code>). Sin ninguna de las dos la app no puede salir a Internet.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card"><p class="ok-text">✅ Estás al día: no hay versiones más nuevas que <strong>v<?= e($current) ?></strong>.</p></div>
<?php endif; ?>

<h3 class="section-title">Últimas actualizaciones aplicadas</h3>
<?php if (!$log): ?>
  <div class="card empty">Aún no hay registros de actualización.</div>
<?php endif; ?>
<?php foreach ($log as $entry): ?>
  <div class="card admin-row">
    <div>
      <strong><?= e($entry['event']) ?></strong>
      <span class="muted"><?= e($entry['tag']) ?> · <?= e($entry['detail']) ?></span>
    </div>
    <span class="muted"><?= e($entry['date']) ?></span>
  </div>
<?php endforeach; ?>

<h3 class="section-title">Cómo funciona</h3>
<div class="card">
  <ol class="help-list">
    <li>Cada <?= update_check_hours() ?> h (cuando un administrador entra), la app consulta
      <em>https://github.com/<?= e(update_github_repo()) ?>/releases/latest</em>.</li>
    <li>Si hay versión nueva recibes un aviso en la campanilla 🔔, un distintivo en el menú
      y este banner con el botón <strong>Actualizar ahora</strong>.</li>
    <li>Al pulsarlo se descargan los archivos de la release desde GitHub y se copian encima
      de los actuales. <strong>config.php y uploads/ nunca se tocan.</strong></li>
    <li>Las migraciones de base de datos se aplican solas y los navegadores recargan la app
      sin caché antigua.</li>
  </ol>
  <h4>Si algún día falla la comprobación</h4>
  <p class="muted">La app necesita poder salir a Internet por HTTPS (usa <code>cURL</code> y, si no
    lo tiene, los flujos de PHP). Con el repositorio
    <a href="https://github.com/<?= e(update_github_repo()) ?>" target="_blank" rel="noopener"><?= e(update_github_repo()) ?></a>
    en <strong>público</strong> no hace falta token. Si algún día lo haces privado, añade
    <code>define('GITHUB_TOKEN', 'ghp_...');</code> en <code>config.php</code> con un token
    fine-grained de solo lectura. La propia pantalla te dice qué falta, con un diagnóstico completo.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>