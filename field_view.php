<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT f.*, u.username owner FROM ' . t('game_fields') . ' f JOIN ' . t('users') . ' u ON u.id = f.created_by WHERE f.id = ?');
$stmt->execute([$id]);
$f = $stmt->fetch();
if (!$f) redirect('fields.php');

$canEdit = is_admin() || (int)$f['created_by'] === (int)$me['id'];

/* Acciones de edición / borrado */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    verify_csrf();
    if (($_POST['field_action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM ' . t('game_fields') . ' WHERE id = ?')->execute([$id]);
        $_SESSION['flash'] = ['ok', 'Campo eliminado.'];
        redirect('fields.php');
    }
    if (($_POST['field_action'] ?? '') === 'publish') {
        db()->prepare('UPDATE ' . t('game_fields') . ' SET is_approved = 1 WHERE id = ?')->execute([$id]);
        $_SESSION['flash'] = ['ok', 'Campo publicado.'];
        redirect('field_view.php?id=' . $id);
    }
}

$page_title = $f['name'];
$page_subtitle = $f['location'] ?: '';
require_once __DIR__ . '/includes/header.php';
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<section class="card field-detail">
  <?php if ($f['image']): ?>
    <img class="field-hero" src="<?= e($f['image']) ?>" alt="<?= e($f['name']) ?>">
  <?php endif; ?>

  <div class="field-detail-top">
    <div>
      <h2>🎯 <?= e($f['name']) ?></h2>
      <p class="muted">Dado de alta por <a href="profile.php?id=<?= (int)$f['created_by'] ?>"><?= e($f['owner']) ?></a> · <?= date('d/m/Y', strtotime($f['created_at'])) ?></p>
    </div>
    <?php if (!$f['is_approved']): ?><span class="badge warn">⏳ Pendiente de aprobación</span><?php endif; ?>
  </div>

  <div class="field-details-grid">
    <div class="info-item"><span>📍 Ubicación</span><strong><?= e($f['location'] ?: '—') ?></strong></div>
    <div class="info-item"><span>🎮 Tipo de juego</span><strong><?= e($f['game_type'] ?: '—') ?></strong></div>
    <div class="info-item"><span>👥 Capacidad</span><strong><?= (int)$f['capacity'] ? 'Hasta ' . (int)$f['capacity'] . ' jugadores' : '—' ?></strong></div>
    <div class="info-item"><span>💰 Precio</span><strong><?= e($f['price'] ?: '—') ?></strong></div>
    <div class="info-item"><span>📞 Contacto</span><strong><?= e($f['contact'] ?: '—') ?></strong></div>
  </div>

  <?php if ($f['description']): ?>
    <h3 class="section-title">Descripción</h3>
    <p class="field-text"><?= nl2br(e($f['description'])) ?></p>
  <?php endif; ?>

  <?php if ($f['game_requirements']): ?>
    <h3 class="section-title">Requisitos de juego</h3>
    <div class="requirements-box"><?= nl2br(e($f['game_requirements'])) ?></div>
  <?php endif; ?>

  <?php if ($canEdit): ?>
    <div class="field-detail-actions">
      <a class="btn btn-primary" href="field_edit.php?id=<?= (int)$f['id'] ?>">✏️ Editar campo</a>
      <?php if (is_admin() && !$f['is_approved']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="field_action" value="publish">
          <button class="btn btn-ok">✅ Publicar</button></form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('¿Eliminar este campo?')"><?= csrf_field() ?><input type="hidden" name="field_action" value="delete">
        <button class="btn btn-danger">🗑️ Eliminar</button></form>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>