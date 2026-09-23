<?php
$page_title = 'Producto';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM ' . t('products') . ' WHERE id = ? AND active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) redirect('store.php');

$page_title = $product['name'];
$buyUrl = product_buy_url($product);
$payMethods = store_payment_methods();

require_once __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb"><a href="store.php">🛒 Tienda</a> <span>›</span> <strong><?= e($product['name']) ?></strong></nav>

<section class="card product-detail">
  <div class="product-detail-grid">
    <div class="product-detail-media">
      <?php if ($product['image']): ?>
        <img class="product-detail-img" src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
      <?php else: ?>
        <div class="product-detail-img placeholder"><span>🎯</span><?= e($product['name']) ?></div>
      <?php endif; ?>
    </div>

    <div class="product-detail-info">
      <h2><?= e($product['name']) ?></h2>
      <?php if ($product['price']): ?><span class="product-price"><?= e($product['price']) ?></span><?php endif; ?>

      <?php if ($product['description']): ?>
        <p class="product-detail-desc"><?= nl2br(e($product['description'])) ?></p>
      <?php endif; ?>

      <div class="product-detail-actions">
        <?php if ($buyUrl): ?>
          <a class="btn btn-buy big" href="<?= e($buyUrl) ?>" target="_blank" rel="noopener sponsored">🛒 Comprar ahora</a>
          <p class="muted store-affiliate-note">Se abre la tienda de afiliado en una pestaña nueva para completar la compra.</p>
        <?php else: ?>
          <span class="btn btn-buy disabled">Consultar disponibilidad</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($payMethods): ?>
    <div class="pay-section">
      <h3>💳 Formas de pago</h3>
      <div class="pay-methods">
        <?php foreach ($payMethods as $m): ?>
          <div class="pay-method">
            <span class="pay-icon"><?= e($m['icon']) ?></span>
            <div>
              <strong><?= e($m['label']) ?></strong>
              <?php if ($m['meta']): ?>
                <?php if ($m['method_key'] === 'paypal' && strpos($m['meta'], 'http') === 0): ?>
                  <a href="<?= e($m['meta']) ?>" target="_blank" rel="noopener"><?= e(parse_url($m['meta'], PHP_URL_HOST)) ?></a>
                <?php else: ?>
                  <span class="pay-meta"><?= e($m['meta']) ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>