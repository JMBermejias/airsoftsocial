<?php
$page_title = 'Gestionar tienda';
require_once __DIR__ . '/includes/functions.php';
require_admin();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM ' . t('products') . ' WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
$stmt = db()->query('SELECT * FROM ' . t('products') . ' ORDER BY sort_order ASC, id DESC');
$products = $stmt->fetchAll();
$payMethods = db()->query('SELECT * FROM ' . t('payment_methods') . ' ORDER BY sort_order ASC, id ASC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="two-col">
  <div>
    <h3 class="section-title"><?= $editing ? 'Editar producto' : 'Añadir producto' ?></h3>
    <form method="post" action="actions/product.php" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <label>Nombre del producto *
        <input type="text" name="name" required value="<?= e($editing['name'] ?? '') ?>">
      </label>
      <div class="form-row">
        <label>Precio
          <input type="text" name="price" value="<?= e($editing['price'] ?? '') ?>" placeholder="Ej: 59,90 €">
        </label>
        <label>Orden (menor = primero)
          <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
        </label>
      </div>
      <label>Enlace de compra (URL de tu tienda)
        <input type="url" name="url" id="prod-url" value="<?= e($editing['url'] ?? '') ?>" placeholder="https://tu-tienda.es/producto">
      </label>
      <p class="muted">
        <button type="button" class="btn btn-small btn-ghost" id="btn-fetch-url">✨ Rellenar datos del enlace</button>
        <span id="fetch-status"></span>
      </p>
      <label>Descripción
        <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
      </label>
      <div class="field-label">Imagen de producto</div>
      <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Subir imagen</label>
      <input type="hidden" name="image_imported" id="image-imported" value="">
      <?php if (!empty($editing['image'])): ?><img class="thumb" src="<?= e($editing['image']) ?>" alt=""><?php endif; ?>
      <label class="tool"><input type="checkbox" name="active" value="1" <?= !isset($editing) || $editing['active'] ? 'checked' : '' ?>> Producto activo (visible)</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Añadir producto' ?></button>
        <?php if ($editing): ?><a class="btn btn-ghost" href="store_admin.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <script>
    (function(){
      var btn = document.getElementById('btn-fetch-url');
      if (!btn) return;
      btn.addEventListener('click', function(){
        var url = (document.getElementById('prod-url').value || '').trim();
        var status = document.getElementById('fetch-status');
        if (!url) { status.textContent = 'Introduce primero el enlace.'; return; }
        if (!window.CS_RF) { status.textContent = 'Sesión expirada, recarga la página.'; return; }
        btn.disabled = true;
        status.textContent = 'Leyendo la tienda…';
        var body = new URLSearchParams();
        body.append('csrf', window.CS_RF);
        body.append('url', url);
        fetch('actions/fetch_product.php', { method: 'post', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
          .then(function(r){ return r.json(); })
          .then(function(d){
            btn.disabled = false;
            if (!d.ok) { status.textContent = d.error || 'No se pudo leer el enlace.'; return; }
            var name = document.querySelector('input[name=name]');
            var price = document.querySelector('input[name=price]');
            var desc = document.querySelector('textarea[name=description]');
            if (d.name && name) name.value = d.name;
            if (d.price && price) price.value = d.price;
            if (d.description && desc) desc.value = d.description;
            var imp = document.getElementById('image-imported');
            if (d.image && imp) imp.value = d.image;
            status.textContent = 'Datos cargados. Revisa y pulsa Guardar.';
          })
          .catch(function(){ btn.disabled = false; status.textContent = 'Error de red. Inténtalo de nuevo.'; });
      });
    })();
    </script>
  </div>

  <div>
    <h3 class="section-title">Productos actuales</h3>
    <?php if (!$products): ?><div class="card empty">No hay productos todavía.</div><?php endif; ?>
    <?php foreach ($products as $p): ?>
      <div class="card product-admin-row">
        <?php if ($p['image']): ?><img class="thumb" src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
        <div>
          <strong><?= e($p['name']) ?></strong>
          <span class="muted"> <?= e($p['price'] ?? '') ?> · <?= $p['active'] ? 'visible' : 'oculto' ?></span>
        </div>
        <div class="row-actions">
          <a class="btn btn-small btn-ghost" href="store_admin.php?edit=<?= (int)$p['id'] ?>">✏️</a>
          <form method="post" action="actions/product.php" onsubmit="return confirm('¿Eliminar producto?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-small btn-danger" type="submit">🗑️</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<h3 class="section-title">💳 Formas de pago aceptadas</h3>
<div class="card">
  <p class="muted">Estas formas de pago se muestran en la ficha de cada producto. Marca las que aceptas, indica el dato público (URL de PayPal, teléfono de Bizum, IBAN, nota…) y el orden.</p>
  <form method="post" action="actions/store_payment.php">
    <?= csrf_field() ?>
    <?php foreach ($payMethods as $m): ?>
      <div class="pay-admin-row">
        <label class="pay-toggle">
          <input type="checkbox" name="enabled[]" value="<?= e($m['method_key']) ?>" <?= $m['is_enabled'] ? 'checked' : '' ?>>
          <span class="pay-icon"><?= e($m['icon']) ?></span>
          <strong><?= e($m['label']) ?></strong>
        </label>
        <input type="text" name="meta[<?= e($m['method_key']) ?>]" value="<?= e($m['meta']) ?>" placeholder="Dato público (URL / teléfono / IBAN / nota)">
        <label class="pay-sort">Orden
          <input type="number" name="sort[<?= e($m['method_key']) ?>]" value="<?= (int)$m['sort_order'] ?>" style="width:70px">
        </label>
      </div>
    <?php endforeach; ?>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Guardar formas de pago</button></div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>