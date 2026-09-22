<?php
$page_title = 'Tienda online';
require_once __DIR__ . '/includes/functions.php';
require_login();

$categoria = trim($_GET['cat'] ?? '');
$sql = 'SELECT * FROM ' . t('products') . ' WHERE active = 1';
$params = [];
if ($categoria !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ?)';
    $like = '%' . $categoria . '%';
    array_push($params, $like, $like);
}
$sql .= ' ORDER BY sort_order ASC, id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <form method="get" action="store.php" class="search-form">
    <input type="text" name="cat" class="search-input" value="<?= e($categoria) ?>" placeholder="Buscar producto…">
    <button class="btn btn-ghost" type="submit">Buscar</button>
  </form>
  <?php if (is_admin()): ?><a class="btn btn-primary" href="store_admin.php">⚙️ Gestionar tienda</a><?php endif; ?>
</div>

<?php if (!$products): ?>
  <div class="card empty">No hay productos disponibles en la tienda.</div>
<?php endif; ?>

<div class="grid-products">
  <?php foreach ($products as $p): ?>
    <article class="card product-full">
      <?php if ($p['image']): ?>
        <img class="product-img big" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      <?php else: ?>
        <div class="product-img big placeholder"><span>🎯</span><?= e($p['name']) ?></div>
      <?php endif; ?>
      <div class="product-body">
        <h3><?= e($p['name']) ?></h3>
        <?php if ($p['description']): ?><p class="product-desc"><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
        <?php if ($p['price']): ?><span class="product-price"><?= e($p['price']) ?></span><?php endif; ?>
        <?php if ($p['url']): ?>
          <a class="btn btn-buy" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">🛒 Comprar en la tienda</a>
        <?php else: ?>
          <span class="btn btn-buy disabled">Consultar disponibilidad</span>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>