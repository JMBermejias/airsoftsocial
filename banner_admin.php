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

/* Se mide la imagen de los anuncios guardados que aún no se han medido (una
 * sola vez cada una). Así el banner se enseña bien desde la primera visita,
 * sin tener que volver a guardarlo. Con límite para no alargar la página. */
$mids = 0;
foreach ($banners as $b) {
    if ($mids >= 4) break;
    $im = trim((string)($b['image'] ?? ''));
    if ($im === '' || !preg_match('#^(https?://|uploads/)#i', $im)) continue;
    $mids++;
    banner_image_size($im);
}

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<h3 class="section-title">Vista previa</h3>
<p class="muted" style="margin-top:-6px">
  Así se verá arriba del área de trabajo, a <strong>728 × 90 px</strong>, el ancho de la columna.
  Se actualiza mientras escribes. El título es opcional: si lo dejas vacío y subes
  una imagen, el banner se verá solo con la imagen.
</p>
<div class="ad-banner" id="banner-preview" data-image="<?= e($editing['image'] ?? '') ?>">
  <div class="ad-banner-link"><div class="ad-banner-body">
    <strong class="ad-banner-title">El título del anuncio aparece aquí</strong>
    <p class="ad-banner-desc">Y debajo su descripción, corta si es muy larga.</p>
  </div></div>
</div>

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
      <label>Título del anuncio <span class="muted">(opcional)</span>
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
      <p class="muted" style="margin:-4px 0 8px">
        Sirve cualquier imagen: la app mide cómo de apaisada es y la enseña como
        quieras. Una <strong>apaisada de 728 × 90 px</strong> se ve a pantalla
        completa dentro del banner; un <strong>logo cuadrado</strong> o una foto
        de producto se muestran enteros a la izquierda, sin recortarlos. Si no
        subes ninguna, se usa la que se detecte en el enlace.
      </p>
      <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Subir imagen</label>
      <input type="hidden" name="image_imported" id="image-imported" value="">
      <?php if (!empty($editing['image'])): ?><img class="thumb" src="<?= e($editing['image']) ?>" alt=""><?php endif; ?>
      <label>URL de la imagen (solo si no se ha importado sola)
        <input type="url" name="image_url" id="image-url" placeholder="https://.../728x90.jpg" value="" autocomplete="off">
      </label>
      <p class="muted" style="margin:-4px 0 8px">
        Algunas webs no dejan descargar sus imágenes. Si al rellenar el enlace te
        avisa de eso, abre el anuncio, haz clic derecho sobre la imagen y pega aquí
        su dirección: la app la descargará al guardar. Si solo hay un icono
        pequeño, sube tú una imagen de 728 × 90 con «📷 Subir imagen».
      </p>
      <label class="tool"><input type="checkbox" name="active" value="1" <?= !isset($editing) || $editing['active'] ? 'checked' : '' ?>> Anuncio visible</label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Guardar cambios' : 'Añadir anuncio' ?></button>
        <?php if ($editing): ?><a class="btn btn-ghost" href="banner_admin.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <script>
    (function(){
      /* ---------- Vista previa 728x90 (se redibuja al escribir) ---------- */
      var pv = document.getElementById('banner-preview');
      var field = function(sel){ return document.querySelector(sel); };
      var esc = function(s){
        return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
          return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
      };
      var picked = '';   /* imagen elegida en este momento */
      var wide = false;  /* ¿la imagen es apaisada? (la decide el navegador al cargarla) */

      function draw(img, isWide){
        if (!pv) return;
        var title = (field('input[name=title]') || {}).value || '';
        var desc  = (field('textarea[name=description]') || {}).value || '';
        var html = '';
        if (img && isWide) html += '<span class="ad-banner-cover"><img src="' + esc(img) + '" alt=""></span>';
        else if (img)      html += '<div class="ad-banner-img"><img src="' + esc(img) + '" alt=""></div>';
        /* Sin «📢 Publicidad» ni «Ver el anuncio →»: el banner entero es el
         * enlace, igual que en la app (ad_banner_html). */
        var body = (title.trim() ? '<strong class="ad-banner-title">' + esc(title.trim()) + '</strong>' : '')
                 + (desc.trim()  ? '<p class="ad-banner-desc">' + esc(desc.trim()) + '</p>' : '');
        if (body) html += '<div class="ad-banner-body">' + body + '</div>';
        pv.className = 'ad-banner' + (isWide ? ' ad-banner-wide' : '');
        pv.innerHTML = html;
        /* Al cargarse la imagen ya sabemos su proporción real: si es apaisada
         * (tipo 728x90) se dibuja a pantalla completa; si no, como miniatura. */
        var im = pv.querySelector('img');
        if (im) im.addEventListener('load', function () {
          var r = im.naturalWidth / im.naturalHeight;
          if (r >= 7.5 && !isWide) draw(img, true);
          else if (r < 7.5 && isWide) draw(img, false);
        });
      }
      function paint(){
        if (!pv) return;
        var imp = (field('#image-imported') || {}).value || '';
        var man = ((field('#image-url') || {}).value || '').trim();
        var img = picked || man || imp || pv.getAttribute('data-image') || '';
        draw(img, img ? wide : false);
      }
      if (pv) {
        ['input[name=title]', 'textarea[name=description]', '#banner-source', '#banner-url', '#image-url']
          .forEach(function(sel){
            var el = field(sel);
            if (el) el.addEventListener('input', paint);
          });
        var file = field('input[type=file]');
        if (file) file.addEventListener('change', function(){
          if (picked) { try { URL.revokeObjectURL(picked); } catch (e) {} }
          picked = (file.files && file.files[0]) ? URL.createObjectURL(file.files[0]) : '';
          wide = false;
          paint();
        });
        paint();
      }

      /* ---------- Rellenar los datos desde el enlace ---------- */
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
            wide = !!d.wide;
            paint();
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
