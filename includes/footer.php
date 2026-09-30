<?php
require_once __DIR__ . '/functions.php';
$store_products = [];
try {
    $st = db()->query('SELECT * FROM ' . t('products') . ' WHERE active = 1 ORDER BY sort_order ASC, id DESC LIMIT 8');
    $store_products = $st->fetchAll();
} catch (PDOException $e) {
    $store_products = [];
}
$me = current_user();
?>
  </main>

  <!-- ===================== SIDEBAR DERECHO: TIENDA ONLINE (siempre visible) ===================== -->
  <aside class="sidebar store-sidebar">
    <div class="store-head">
      <span class="store-ico">🛒</span>
      <div>
        <h3>Tienda online</h3>
        <small>Equipación y accesorios</small>
      </div>
    </div>

    <?php if ($store_products): ?>
      <div class="store-list">
        <?php foreach ($store_products as $p): ?>
          <article class="product-card">
            <a href="store_product.php?id=<?= (int)$p['id'] ?>">
              <?php if ($p['image']): ?>
                <img class="product-img" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <?php else: ?>
                <div class="product-img placeholder">
                  <span>🎯</span>
                  <?= e($p['name']) ?>
                </div>
              <?php endif; ?>
            </a>
            <div class="product-body">
              <h4><a href="store_product.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h4>
              <?php if ($p['description']): ?><p class="product-desc"><?= e(mb_strimwidth($p['description'], 0, 90, '…')) ?></p><?php endif; ?>
              <?php if ($p['price']): ?><span class="product-price"><?= e($p['price']) ?></span><?php endif; ?>
              <?php if (product_buy_url($p)): ?>
                <a class="btn btn-buy" href="<?= e(product_buy_url($p)) ?>" target="_blank" rel="noopener sponsored">Comprar</a>
              <?php else: ?>
                <a class="btn btn-buy" href="store_product.php?id=<?= (int)$p['id'] ?>">Ver producto</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <a class="store-more" href="store.php">Ver toda la tienda →</a>
    <?php else: ?>
      <p class="empty">La tienda aún no tiene productos.</p>
      <?php if (is_admin()): ?><a class="btn btn-primary" href="store_admin.php">Añadir productos</a><?php endif; ?>
    <?php endif; ?>

    <?php if (is_admin()): ?>
      <a class="btn btn-ghost store-admin-link" href="store_admin.php">⚙️ Gestionar tienda</a>
    <?php endif; ?>
  </aside>

</div>

<div id="story-viewer" class="story-viewer hidden">
  <button class="story-close" onclick="closeStory()">✕</button>
  <div class="story-stage">
    <div class="story-top"><img id="sv-avatar" class="avatar" src="" alt=""><span id="sv-user">…</span></div>
    <p id="sv-text" class="story-text"></p>
    <img id="sv-img" class="story-img" src="" alt="historia">
    <video id="sv-video" class="story-video" controls playsinline preload="metadata"></video>
  </div>
  <div id="story-progress"></div>
</div>

<script src="assets/js/app.js?v=7"></script>
<script>
/* Registro del service worker (PWA instalable). Requiere HTTPS o localhost. */
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
  navigator.serviceWorker.register('service-worker.js').catch(function () {});
}
</script>
</body>
</html>