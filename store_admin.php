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
        <input type="url" name="url" value="<?= e($editing['url'] ?? '') ?>" placeholder="https://tu-tienda.es/producto">
      </label>
      <label>Descripción
        <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
      </label>
      <div class="field-label">Imagen de producto</div>
      <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Subir imagen</label>
      <?php if (!empty($editing['image'])): ?><img class="thumb" src="<?= e($editing['image']) ?>" alt=""><?php endif; ?>
      <label class="tool"><input type="checkbox" name="active" value="1" <?= !isset($editing) || $editing['active'] ? 'checked' : '' ?>> Producto activo (visible)</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Añadir producto' ?></button>
        <?php if ($editing): ?><a class="btn btn-ghost" href="store_admin.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>