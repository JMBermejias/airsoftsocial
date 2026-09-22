<?php
$page_title = 'Campos de juego';
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

$q = trim($_GET['q'] ?? '');
$ql = '%' . $q . '%';

$sql = 'SELECT f.*, u.username owner FROM ' . t('game_fields') . ' f JOIN ' . t('users') . ' u ON u.id = f.created_by WHERE f.is_approved = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (f.name LIKE ? OR f.location LIKE ? OR f.game_type LIKE ?)';
    array_push($params, $ql, $ql, $ql);
}
$sql .= ' ORDER BY f.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$fields = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <form method="get" action="fields.php" class="search-form">
    <input type="text" name="q" class="search-input" value="<?= e($q) ?>" placeholder="Buscar campo, ubicación o tipo de juego…">
    <button class="btn btn-ghost" type="submit">Buscar</button>
  </form>
  <a class="btn btn-primary" href="field_edit.php">➕ Dar de alta un campo</a>
</div>

<?php if (!$fields): ?>
  <div class="card empty">No hay campos publicados. ¡Da de alta el primero!</div>
<?php endif; ?>

<div class="grid-fields">
  <?php foreach ($fields as $f): ?>
    <article class="card field-card">
      <?php if ($f['image']): ?>
        <img class="field-img" src="<?= e($f['image']) ?>" alt="<?= e($f['name']) ?>" loading="lazy">
      <?php endif; ?>
      <div class="field-body">
        <h3><a href="field_view.php?id=<?= (int)$f['id'] ?>"><?= e($f['name']) ?></a></h3>
        <p class="muted">📍 <?= e($f['location'] ?: 'Ubicación no indicada') ?></p>
        <div class="field-tags">
          <?php if ($f['game_type']): ?><span class="tag"><?= e($f['game_type']) ?></span><?php endif; ?>
          <?php if ($f['capacity']): ?><span class="tag">👥 <?= (int)$f['capacity'] ?> jugadores</span><?php endif; ?>
          <?php if ($f['price']): ?><span class="tag">€ <?= e($f['price']) ?></span><?php endif; ?>
        </div>
        <p class="field-desc"><?= e(mb_strimwidth($f['description'] ?? '', 0, 140, '…')) ?></p>
        <a class="btn btn-small btn-ghost" href="field_view.php?id=<?= (int)$f['id'] ?>">Ver detalle</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>