<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['friend_action'])) {
    verify_csrf();
    $fid = (int)($_POST['id'] ?? 0);
    $act = $_POST['friend_action'];
    if ($fid === (int)$me['id']) { redirect('friends.php'); }

    if ($act === 'add') {
        if (friendship_status($me['id'], $fid) === 'none') send_friend_request($me['id'], $fid);
    } elseif ($act === 'accept') {
        accept_friend_request($me['id'], $fid);
    } elseif ($act === 'reject') {
        reject_friend_request($me['id'], $fid);
    } elseif ($act === 'remove') {
        remove_friendship($me['id'], $fid);
    } elseif ($act === 'cancel') {
        $stmt = db()->prepare('DELETE FROM ' . t('friendships') . ' WHERE user_id = ? AND friend_id = ? AND status = "pending" AND requester_id = ?');
        $stmt->execute([$me['id'], $fid, $me['id']]);
    }
    redirect('friends.php');
}

/* Amigos actuales */
$stmt = db()->prepare('SELECT u.*, f.created_at rel FROM ' . t('friendships') . ' f JOIN ' . t('users') . ' u ON u.id = CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END WHERE f.status = "accepted" AND (f.user_id = ? OR f.friend_id = ?) ORDER BY u.username');
$stmt->execute([$me['id'], $me['id'], $me['id']]);
$friends = $stmt->fetchAll();

/* Solicitudes recibidas */
$stmt = db()->prepare('SELECT u.*, f.created_at rel FROM ' . t('friendships') . ' f JOIN ' . t('users') . ' u ON u.id = f.requester_id WHERE f.friend_id = ? AND f.status = "pending" AND f.requester_id <> ?');
$stmt->execute([$me['id'], $me['id']]);
$requests = $stmt->fetchAll();

/* Solicitudes enviadas (pendientes) */
$stmt = db()->prepare('SELECT u.* FROM ' . t('friendships') . ' f JOIN ' . t('users') . ' u ON u.id = f.friend_id WHERE f.user_id = ? AND f.status = "pending"');
$stmt->execute([$me['id']]);
$sent = $stmt->fetchAll();

/* Todos los usuarios para buscar nuevos */
$stmt = db()->query('SELECT id, username, avatar, full_name, location, experience_level FROM ' . t('users') . ' ORDER BY username LIMIT 500');
$all = array_filter($stmt->fetchAll(), fn($u) => (int)$u['id'] !== (int)$me['id']);

$page_title = 'Amigos';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Solicitudes recibidas -->
<?php if ($requests): ?>
<section>
  <h3 class="section-title">Solicitudes de amistad (<?= count($requests) ?>)</h3>
  <div class="grid-users">
    <?php foreach ($requests as $u): ?>
      <div class="card user-card">
        <img class="avatar lg" src="<?= avatar_src($u['avatar']) ?>" alt="">
        <a class="user-name" href="profile.php?id=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a>
        <span class="muted"><?= e($u['location'] ?: 'Sin ubicación') ?></span>
        <div class="user-actions">
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="friend_action" value="accept"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-primary btn-small">Aceptar</button>
          </form>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="friend_action" value="reject"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-ghost btn-small">Rechazar</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Mis amigos -->
<section>
  <h3 class="section-title">Mis amigos (<?= count($friends) ?>)</h3>
  <?php if ($friends): ?>
  <div class="grid-users">
    <?php foreach ($friends as $u): ?>
      <div class="card user-card">
        <img class="avatar lg" src="<?= avatar_src($u['avatar']) ?>" alt="">
        <a class="user-name" href="profile.php?id=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a>
        <span class="muted"><?= e($u['location'] ?: '—') ?></span>
        <form method="post" onsubmit="return confirm('¿Eliminar a este amigo?')">
          <?= csrf_field() ?><input type="hidden" name="friend_action" value="remove"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-danger btn-small">Eliminar amigo</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="card empty">Todavía no tienes amigos. Busca usuarios más abajo y envíales una solicitud.</div>
  <?php endif; ?>
</section>

<!-- Enviadas pendientes -->
<?php if ($sent): ?>
<section>
  <h3 class="section-title">Solicitudes enviadas</h3>
  <div class="grid-users">
    <?php foreach ($sent as $u): ?>
      <div class="card user-card pending">
        <img class="avatar lg" src="<?= avatar_src($u['avatar']) ?>" alt="">
        <a class="user-name" href="profile.php?id=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a>
        <span class="muted">⏳ Pendiente de aceptar</span>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="friend_action" value="cancel"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-ghost btn-small">Cancelar solicitud</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Buscar / añadir usuarios -->
<section>
  <h3 class="section-title">Añadir amigos</h3>
  <input type="text" id="user-search" class="search-input" placeholder="Buscar por usuario o ubicación…" autocomplete="off">
  <div class="grid-users" id="all-users">
    <?php foreach ($all as $u): $st = friendship_status($me['id'], (int)$u['id']); ?>
      <div class="card user-card <?= $st; ?>" data-search="<?= e(strtolower(($u['username'] ?? '') . ' ' . ($u['full_name'] ?? '') . ' ' . ($u['location'] ?? ''))) ?>">
        <img class="avatar lg" src="<?= avatar_src($u['avatar']) ?>" alt="">
        <a class="user-name" href="profile.php?id=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a>
        <span class="muted"><?= e($u['location'] ?: '—') ?></span>
        <?php if ($st === 'none'): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="friend_action" value="add"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-primary btn-small">➕ Añadir</button>
          </form>
        <?php elseif ($st === 'friends'): ?>
          <span class="status ok">✅ Amigos</span>
        <?php elseif ($st === 'pending_received'): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="friend_action" value="accept"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-primary btn-small">Te lo pidió ✅</button>
          </form>
        <?php else: ?>
          <span class="status">⏳ Pendiente</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>