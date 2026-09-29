<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

if (!empty($_POST['profile_action'])) {
    verify_csrf();
    $fid = (int)($_POST['id'] ?? 0);
    $act = $_POST['profile_action'];
    if ($fid !== (int)$me['id']) {
        if ($act === 'add') send_friend_request($me['id'], $fid);
        elseif ($act === 'accept') accept_friend_request($me['id'], $fid);
        elseif ($act === 'reject') reject_friend_request($me['id'], $fid);
        elseif ($act === 'remove') remove_friendship($me['id'], $fid);
        elseif ($act === 'cancel') {
            db()->prepare('DELETE FROM ' . t('friendships') . ' WHERE user_id = ? AND friend_id = ? AND status = "pending" AND requester_id = ?')->execute([$me['id'], $fid, $me['id']]);
        }
    }
    redirect('profile.php?id=' . $fid);
}

$id = (int)($_GET['id'] ?? $me['id']);
$stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect('feed.php');

$status = friendship_status($me['id'], (int)$user['id']);
$isOwner = (int)$user['id'] === (int)$me['id'];
$canSeePosts = $isOwner || is_admin() || $status === 'friends';

$stmt = db()->prepare('SELECT COUNT(*) c FROM ' . t('friendships') . ' WHERE status = "accepted" AND (user_id = ? OR friend_id = ?)');
$stmt->execute([$id, $id]);
$fcount = $stmt->fetch()['c'];

$page_title = $user['username'];
$page_subtitle = $user['full_name'] ?: 'Miembro de la red';
require_once __DIR__ . '/includes/header.php';
?>

<section class="card profile-card">
  <div class="profile-banner"></div>
  <div class="profile-pic">
    <img class="avatar xl" src="<?= avatar_src($user['avatar']) ?>" alt="">
  </div>
  <div class="profile-info">
    <h2><?= e($user['username']) ?>
      <?= rank_badge($user) ?>
      <?php if ($user['is_admin']): ?><span class="admin-badge">Administrador</span><?php endif; ?>
    </h2>
    <p class="muted"><?= e($user['full_name'] ?: 'Miembro de Airsoft Social') ?> · se unió el <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
    <div class="profile-stats">
      <span><strong><?= $fcount ?></strong> amigos</span>
      <span><strong><?= $isOwner || $canSeePosts ? 'participa en la red' : '…' ?></strong></span>
    </div>

    <?php if ($user['bio']): ?><p class="bio"><?= nl2br(e($user['bio'])) ?></p><?php endif; ?>

    <ul class="profile-details">
      <li>🎖️ Rango militar: <strong><?= e(military_rank($user)) ?></strong> (<?= days_registered($user) ?> días de antigüedad)</li>
      <li>📍 Ubicación: <strong><?= e($user['location'] ?: 'No indicada') ?></strong></li>
      <li>🎖️ Experiencia: <strong><?= e($user['experience_level'] ?: 'Sin especificar') ?></strong></li>
      <li>🔥 Estilo de juego: <strong><?= e($user['playing_style'] ?: 'Sin especificar') ?></strong></li>
      <li>🔫 Arma principal: <strong><?= e($user['primary_weapon'] ?: 'Sin especificar') ?></strong></li>
    </ul>

    <div class="profile-actions">
      <?php if ($isOwner): ?>
        <a class="btn btn-primary" href="profile_edit.php">✏️ Editar mi perfil</a>
      <?php elseif ($status === 'none'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="profile_action" value="add"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <button class="btn btn-primary">➕ Añadir como amigo</button></form>
      <?php elseif ($status === 'friends'): ?>
        <form method="post" onsubmit="return confirm('¿Eliminar a este amigo?')"><?= csrf_field() ?><input type="hidden" name="profile_action" value="remove"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <button class="btn btn-ghost">✅ Amigos · Eliminar</button></form>
      <?php elseif ($status === 'pending_received'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="profile_action" value="accept"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <button class="btn btn-primary">✅ Aceptar solicitud</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="profile_action" value="reject"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <button class="btn btn-ghost">Rechazar</button></form>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="profile_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
          <button class="btn btn-ghost">⏳ Solicitud enviada · Cancelar</button></form>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Publicaciones del usuario -->
<section>
  <h3 class="section-title">Publicaciones de <?= e($user['username']) ?></h3>
  <?php if (!$canSeePosts): ?>
    <div class="card empty">🔒 Sus publicaciones solo las ven sus amigos añadidos. ¡Envíale una solicitud de amistad!</div>
  <?php else: ?>
    <?php
    $stmt = db()->prepare('SELECT p.*, (SELECT COUNT(*) FROM ' . t('post_likes') . ' l WHERE l.post_id = p.id) likes FROM ' . t('posts') . ' p WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 30');
    $stmt->execute([$id]);
    $posts = $stmt->fetchAll();
    if (!$posts): ?>
      <div class="card empty">Este usuario aún no ha publicado nada.</div>
    <?php endif; ?>
    <?php foreach ($posts as $p): ?>
      <article class="card post">
        <div class="post-head">
          <img class="avatar" src="<?= avatar_src($user['avatar']) ?>" alt="">
          <div class="post-meta">
            <a class="post-author" href="profile.php?id=<?= (int)$user['id'] ?>"><?= e($user['username']) ?></a>
            <span class="post-time"><?= time_ago($p['created_at']) ?></span>
          </div>
        </div>
        <?php if ($p['content']): ?><div class="post-body"><?= nl2br(e($p['content'])) ?></div><?php endif; ?>
        <?php if ($p['file'] && $p['file_type'] === 'image'): ?>
          <div class="post-file"><a href="<?= e($p['file']) ?>" target="_blank"><img src="<?= e($p['file']) ?>" alt="" loading="lazy"></a></div>
        <?php elseif ($p['file'] && $p['file_type'] === 'pdf'): ?>
          <div class="post-file"><a class="pdf-link" href="<?= e($p['file']) ?>" target="_blank">📄 Ver PDF adjunto</a></div>
        <?php endif; ?>
        <?php if ($p['tags']): ?><div class="post-tags"><?= render_tags($p['tags']) ?></div><?php endif; ?>
        <div class="post-actions"><span class="stat">👍 <?= (int)$p['likes'] ?></span></div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>