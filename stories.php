<?php
$page_title = 'Historias';
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* Historias vistas: propias + de amigos (solo amigos) */
$fids = friend_ids($me['id']);
$fids[] = $me['id'];
$in = implode(',', array_fill(0, count($fids), '?'));
$qs = 'SELECT s.*, u.username, u.avatar FROM ' . t('stories') . ' s JOIN ' . t('users') . ' u ON u.id = s.user_id WHERE s.expires_at > NOW() AND s.user_id IN (' . $in . ') ORDER BY s.created_at DESC';
$stories = db()->prepare($qs);
$stories->execute($fids);
$stories = $stories->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<section class="card" style="max-width:640px">
  <h3>Crea una historia</h3>
  <p class="muted">Las historias duran 24 h y solo las ven tus <strong>amigos añadidos</strong>.</p>
  <form action="actions/story.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <textarea name="content" rows="3" placeholder="¿Qué está pasando?"></textarea>
    <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Añadir imagen</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Publicar historia</button></div>
  </form>
</section>

<section class="stories-grid">
  <?php if (!$stories): ?>
    <div class="card empty">No hay historias activas. Tus historias y las de tus amigos aparecerán aquí.</div>
  <?php endif; ?>
  <?php foreach ($stories as $s): ?>
    <article class="card story-row">
      <button class="story-btn lg" onclick='openStory(<?= json_encode(['user' => $s['username'], 'avatar' => $s['avatar'], 'text' => $s['content'], 'image' => $s['image']], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) ?>)'>
        <span class="story-ring"><img src="<?= avatar_src($s['avatar']) ?>" alt=""></span>
        <span class="story-name"><strong><?= e($s['username']) ?></strong><br><?= time_ago($s['created_at']) ?></span>
      </button>
      <div class="story-preview">
        <?php if ($s['image']): ?><img src="<?= e($s['image']) ?>" alt=""><?php endif; ?>
        <?php if ($s['content']): ?><p><?= e(mb_strimwidth($s['content'], 0, 120, '…')) ?></p><?php endif; ?>
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