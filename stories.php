<?php
$page_title = 'Historias';
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* Historias visibles: propias + de amigos. Permanentes y de 24 h no expiradas. */
$stories = visible_stories($me['id']);

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<section class="card" style="max-width:640px">
  <h3>Crea una historia</h3>
  <p class="muted">Las historias son <strong>permanentes</strong> y solo las ven tus <strong>amigos añadidos</strong>. Marca la casilla «24 h» si quieres que desaparezca automáticamente al pasar un día.</p>
  <form action="actions/story.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <textarea name="content" rows="3" placeholder="¿Qué está pasando?"></textarea>
    <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Añadir imagen</label>
    <label class="check-hint">
      <input type="checkbox" name="expires_24h" value="1"> ⏳ Historia de <strong>24 horas</strong> (se borra sola). Deja la casilla sin marcar para conservarla siempre.
    </label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Publicar historia</button></div>
  </form>
</section>

<section class="stories-grid">
  <?php if (!$stories): ?>
    <div class="card empty">No hay historias. Tus historias permanentes y las de tus amigos (y las de 24 h activas) aparecerán aquí.</div>
  <?php endif; ?>
  <?php foreach ($stories as $s): ?>
    <article class="card story-row">
      <button class="story-btn lg" onclick='openStory(<?= json_encode(['user' => $s['username'], 'avatar' => $s['avatar'], 'text' => $s['content'], 'image' => $s['image']], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) ?>)'>
        <span class="story-ring<?= $s['expires_at'] ? '' : ' perm' ?>"><img src="<?= avatar_src($s['avatar']) ?>" alt=""></span>
        <span class="story-name"><strong><?= e($s['username']) ?></strong><br><?= time_ago($s['created_at']) ?></span>
      </button>
      <div class="story-preview">
        <?php if ($s['image']): ?><img src="<?= e($s['image']) ?>" alt=""><?php endif; ?>
        <?php if ($s['content']): ?><p><?= e(mb_strimwidth($s['content'], 0, 120, '…')) ?></p><?php endif; ?>
        <?php if ($s['expires_at']): ?><span class="story-badge temp" title="Se elimina sola cuando expira">⏳ Restan <?= time_till($s['expires_at']) ?></span>
        <?php else: ?><span class="story-badge perm-badge">♾️ Permanente</span><?php endif; ?>
      </div>
      <?php if ((int)$s['user_id'] === (int)$me['id']): ?>
        <form action="actions/story.php" method="post" onsubmit="return confirm('¿Eliminar esta historia?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-danger btn-small" type="submit">Eliminar</button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>