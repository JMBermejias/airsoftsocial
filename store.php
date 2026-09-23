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
      <a href="store_product.php?id=<?= (int)$p['id'] ?>">
        <?php if ($p['image']): ?>
          <img class="product-img big" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <?php else: ?>
          <div class="product-img big placeholder"><span>🎯</span><?= e($p['name']) ?></div>
        <?php endif; ?>
      </a>
      <div class="product-body">
        <h3><a href="store_product.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h3>
        <?php if ($p['description']): ?><p class="product-desc"><?= e(mb_strimwidth($p['description'], 0, 140, '…')) ?></p><?php endif; ?>
        <?php if ($p['price']): ?><span class="product-price"><?= e($p['price']) ?></span><?php endif; ?>
        <div class="product-card-actions">
          <a class="btn btn-ghost btn-small" href="store_product.php?id=<?= (int)$p['id'] ?>">Ver ficha</a>
          <?php if (product_buy_url($p)): ?>
            <a class="btn btn-buy btn-small" href="<?= e(product_buy_url($p)) ?>" target="_blank" rel="noopener sponsored">🛒 Comprar</a>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>