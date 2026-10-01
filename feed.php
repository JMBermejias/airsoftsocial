<?php
$page_title = 'Noticias';
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* --- Historias visibles (propias + de amigos): permanentes y de 24 h no expiradas --- */
$stories = visible_stories($me['id']);

/* --- Muro (feed) --- */
$fids = friend_ids($me['id']);
$fids[] = $me['id'];
$in = implode(',', array_fill(0, count($fids), '?'));
$params = $fids;
$qs = 'SELECT p.*, u.username, u.avatar, u.full_name, u.created_at AS u_created_at, u.is_admin AS u_is_admin,
        (SELECT COUNT(*) FROM ' . t('post_likes') . ' l WHERE l.post_id = p.id) likes_count,
        (SELECT COUNT(*) FROM ' . t('post_likes') . ' l WHERE l.post_id = p.id AND l.user_id = ' . (int)$me['id'] . ') liked,
        (SELECT COUNT(*) FROM ' . t('post_comments') . ' c WHERE c.post_id = p.id) comments_count
      FROM ' . t('posts') . ' p JOIN ' . t('users') . ' u ON u.id = p.user_id
      WHERE (p.user_id IN (' . $in . ') OR p.is_general = 1)
      ORDER BY p.created_at DESC LIMIT 60';
$stmtFeed = db()->prepare($qs);
$stmtFeed->execute($params);
$feed = $stmtFeed->fetchAll();
/*
 * Nota: p.is_general = 1 -> noticias importantes que el administrador
 * generaliza a TODOS los usuarios, aunque no sean amigos.
 */

$comments = [];
if ($feed) {
    $ids = array_map(fn($p) => (int)$p['id'], $feed);
    $inC = implode(',', array_fill(0, count($ids), '?'));
    $stmtC = db()->prepare('SELECT c.*, u.username, u.avatar, u.created_at AS u_created_at, u.is_admin AS u_is_admin FROM ' . t('post_comments') . ' c JOIN ' . t('users') . ' u ON u.id = c.user_id WHERE c.post_id IN (' . $inC . ') ORDER BY c.created_at ASC');
    $stmtC->execute($ids);
    foreach ($stmtC->fetchAll() as $c) {
        $comments[$c['post_id']][] = $c;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?>
  <div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>

<!-- ===================== HISTORIAS ===================== -->
<section class="stories-bar">
  <button class="story-btn own" onclick="openStoryCreate()">
    <span class="story-ring add">＋</span>
    <span>Tu historia</span>
  </button>
  <?php foreach ($stories as $s): ?>
    <?php if ((int)$s['user_id'] === (int)$me['id']) continue; ?>
    <button class="story-btn" onclick='openStory(<?= json_encode(['user' => $s['username'], 'avatar' => $s['avatar'], 'text' => $s['content'], 'image' => $s['image'], 'video' => $s['video']], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) ?>)'>
      <span class="story-ring"><img src="<?= avatar_src($s['avatar']) ?>" alt=""></span>
      <span><?= e($s['username']) ?></span>
    </button>
  <?php endforeach; ?>
</section>

<div id="story-create" class="modal hidden">
  <form action="actions/story.php" method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h3>Nueva historia</h3>
    <textarea name="content" rows="3" placeholder="¿Qué está pasando en el campo ahora mismo?"></textarea>
    <label class="file-picker">
      <input type="file" name="image" accept="image/*"> 📷 Añadir imagen
    </label>
    <label class="file-picker">
      <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime"> 🎬 Añadir vídeo
    </label>
    <label class="check-hint">
      <input type="checkbox" name="expires_24h" value="1"> ⏳ Historia de <strong>24 horas</strong> (se borra sola). Sin marcar = <strong>permanente</strong>.
    </label>
    <div class="form-actions">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('story-create').classList.add('hidden')">Cancelar</button>
      <button class="btn btn-primary" type="submit">Publicar historia</button>
    </div>
  </form>
</div>

<!-- ===================== COMPOSITOR ===================== -->
<section class="card composer">
  <form action="actions/post.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="composer-top">
      <img class="avatar" src="<?= avatar_src($me['avatar']) ?>" alt="">
      <textarea name="content" rows="2" placeholder="Comparte algo con tu red… (noticias, jugadas, opiniones)"></textarea>
    </div>
    <div class="composer-tools">
      <label class="tool">
        <input type="file" name="file" accept="image/*,application/pdf">
        <span>📷 / 📄 Imagen o PDF</span>
      </label>
      <?php /* El campo de etiquetas y el botón «Publicar» van en la misma fila:
               en el móvil, apilados, el botón quedaba como una barra naranja
               gigante al lado de los campos. Con el envoltorio comparten línea
               y el botón solo ocupa lo que necesita. */ ?>
      <div class="composer-row">
        <input type="text" name="tags" placeholder="#etiquetas separadas por comas" class="tags-input">
        <button class="btn btn-primary" type="submit">Publicar</button>
      </div>
      <?php if (is_admin()): ?>
        <label class="tool general">
          <input type="checkbox" name="is_general" value="1">
          <span>⭐ Noticia general (visible para toda la red)</span>
        </label>
      <?php endif; ?>
    </div>
  </form>
</section>

<!-- ===================== PUBLICACIONES ===================== -->
<section class="feed-list">
  <?php if (!$feed): ?>
    <div class="card empty">Aún no hay publicaciones. ¡Sé el primero en escribir una!</div>
  <?php endif; ?>

  <?php foreach ($feed as $p): ?>
    <article class="card post <?= $p['is_general'] ? 'general' : '' ?>" id="post-<?= (int)$p['id'] ?>">
      <?php if ($p['is_general']): ?>
        <div class="post-banner">⭐ Noticia general de la red</div>
      <?php endif; ?>
      <div class="post-head">
        <a href="profile.php?id=<?= (int)$p['user_id'] ?>">
          <img class="avatar" src="<?= avatar_src($p['avatar']) ?>" alt="">
        </a>
        <div class="post-meta">
          <a class="post-author" href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= e($p['username']) ?></a>
          <?= rank_badge(['is_admin' => $p['u_is_admin'], 'created_at' => $p['u_created_at']]) ?>
          <span class="post-time"><?= time_ago($p['created_at']) ?></span>
        </div>
        <?php if ((int)$p['user_id'] === (int)$me['id'] || is_admin()): ?>
          <form action="actions/post.php" method="post" class="post-del" onsubmit="return confirm('¿Eliminar esta publicación?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" title="Eliminar">🗑️</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($p['content']): ?>
        <div class="post-body">
          <?= nl2br(e($p['content'])) ?>
        </div>
      <?php endif; ?>

      <?php if ($p['file']): ?>
        <div class="post-file">
          <?php if ($p['file_type'] === 'image'): ?>
            <a href="<?= e($p['file']) ?>" target="_blank">
              <img src="<?= e($p['file']) ?>" alt="imagen" loading="lazy">
            </a>
          <?php elseif ($p['file_type'] === 'pdf'): ?>
            <a class="pdf-link" href="<?= e($p['file']) ?>" target="_blank">📄 Ver PDF adjunto</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($p['tags']): ?>
        <div class="post-tags"><?= render_tags($p['tags']) ?></div>
      <?php endif; ?>

      <div class="post-actions">
        <form action="actions/post.php" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="like">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="like-btn <?= $p['liked'] ? 'liked' : '' ?>" type="submit">
            👍 <?= (int)$p['likes_count'] ?>
          </button>
        </form>
        <span class="stat">💬 <?= (int)$p['comments_count'] ?></span>
      </div>

      <div class="post-comments">
        <div class="comment-form">
          <img class="avatar sm" src="<?= avatar_src($me['avatar']) ?>" alt="">
          <form action="actions/post.php" method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="text" name="comment" placeholder="Escribe un comentario…" required>
            <button class="btn btn-small" type="submit">Enviar</button>
          </form>
        </div>
        <?php if (!empty($comments[$p['id']])): ?>
          <?php foreach ($comments[$p['id']] as $c): ?>
            <div class="comment">
              <img class="avatar sm" src="<?= avatar_src($c['avatar']) ?>" alt="">
              <div>
                <a class="c-author" href="profile.php?id=<?= (int)$c['user_id'] ?>"><?= e($c['username']) ?></a>
                <?= rank_badge(['is_admin' => $c['u_is_admin'], 'created_at' => $c['u_created_at']]) ?>
                <span class="c-time"><?= time_ago($c['created_at']) ?></span>
                <p><?= nl2br(e($c['comment'])) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>