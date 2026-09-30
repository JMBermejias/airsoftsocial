<?php
$page_title = 'Gestionar anuncios';
require_once __DIR__ . '/includes/functions.php';
require_admin();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM ' . t('ad_banners') . ' WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
$banners = ad_banners();
$shown = active_ad_banner();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="two-col">
  <div>
    <h3 class="section-title"><?= $editing ? 'Editar anuncio' : 'Añadir anuncio' ?></h3>
    <form method="post" action="actions/banner.php" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <label>Enlace del anuncio *
        <input type="url" name="url" id="banner-url" value="<?= e($editing['url'] ?? '') ?>" placeholder="https://tu-anunciante.es/oferta">
      </label>
      <p class="muted">
        <button type="button" class="btn btn-small btn-ghost" id="btn-fetch-url">✨ Rellenar datos del enlace</button>
        <span id="fetch-status"></span>
      </p>
      <label>Título del anuncio *
        <input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" placeholder="Ej: Envío gratis en toda la peninsula">
      </label>
      <label>Origen del anuncio (se detecta solo)
        <input type="text" name="source" id="banner-source" value="<?= e($editing['source'] ?? '') ?>" placeholder="Ej: YouTube, Amazon.es, tu tienda">
      </label>
      <label>Descripción
        <textarea name="description" rows="3" maxlength="400"><?= e($editing['description'] ?? '') ?></textarea>
      </label>
      <div class="form-row">
        <label>Orden (menor = se muestra primero)
          <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
        </label>
        <div class="field-label">Imagen del banner</div>
      </div>
      <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Subir imagen</label>
      <input type="hidden" name="image_imported" id="image-imported" value="">
      <?php if (!empty($editing['image'])): ?><img class="thumb" src="<?= e($editing['image']) ?>" alt=""><?php endif; ?>
      <label class="tool"><input type="checkbox" name="active" value="1" <?= !isset($editing) || $editing['active'] ? 'checked' : '' ?>> Anuncio visible</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Añadir anuncio' ?></button>
        <?php if ($editing): ?><a class="btn btn-ghost" href="banner_admin.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <script>
    (function(){
      var btn = document.getElementById('btn-fetch-url');
      if (!btn) return;
      btn.addEventListener('click', function(){
        var url = (document.getElementById('banner-url').value || '').trim();
        var status = document.getElementById('fetch-status');
        if (!url) { status.textContent = 'Introduce primero el enlace.'; return; }
        if (!window.CS_RF) { status.textContent = 'Sesión expirada, recarga la página.'; return; }
        btn.disabled = true;
        status.textContent = 'Leyendo el enlace…';
        var body = new URLSearchParams();
        body.append('csrf', window.CS_RF);
        body.append('url', url);
        fetch('actions/fetch_banner.php', { method: 'post', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
          .then(function(r){ return r.json(); })
          .then(function(d){
            btn.disabled = false;
            if (!d.ok) { status.textContent = d.error || 'No se pudo leer el enlace.'; return; }
            var f = function(sel, val){ var el = document.querySelector(sel); if (val && el && !el.value) el.value = val; };
            f('input[name=title]', d.title);
            f('textarea[name=description]', d.description);
            f('#banner-source', d.source);
            var imp = document.getElementById('image-imported');
            if (d.image && imp) imp.value = d.image;
            status.textContent = d.warning || ('Origen reconocido: ' + (d.source || 'desconocido') + '. Revisa y pulsa Guardar.');
          })
          .catch(function(){ btn.disabled = false; status.textContent = 'Error de red. Inténtalo de nuevo.'; });
      });
    })();
    </script>
  </div>

  <div>
    <h3 class="section-title">Anuncios actuales</h3>
    <p class="muted">En la parte alta del área de trabajo se muestra el primer anuncio visible (orden menor primero).</p>
    <?php if (!$banners): ?><div class="card empty">Todavía no hay anuncios.</div><?php endif; ?>
    <?php foreach ($banners as $b): ?>
      <div class="card product-admin-row">
        <?php if (!empty($b['image'])): ?><img class="thumb" src="<?= e($b['image']) ?>" alt=""><?php endif; ?>
        <div>
          <strong><?= e($b['title']) ?></strong>
          <span class="muted"> <?= e($b['source'] ?? 'origen ?') ?> · <?= $b['active'] ? 'visible' : 'oculto' ?><?= ($shown && (int)$shown['id'] === (int)$b['id']) ? ' · en pantalla' : '' ?></span>
        </div>
        <div class="row-actions">
          <a class="btn btn-small btn-ghost" href="banner_admin.php?edit=<?= (int)$b['id'] ?>" title="Editar">✏️</a>
          <form method="post" action="actions/banner.php" onsubmit="return confirm('¿Eliminar el anuncio?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-small btn-danger" type="submit" title="Eliminar">🗑️</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
