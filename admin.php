<?php
$page_title = 'Panel de administración';
require_once __DIR__ . '/includes/functions.php';
require_admin();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $target = (int)($_POST['target'] ?? 0);
    $what = $_POST['what'] ?? '';

    if ($what === 'approve_field') {
        db()->prepare('UPDATE ' . t('game_fields') . ' SET is_approved = 1 WHERE id = ?')->execute([$target]);
        $_SESSION['flash'] = ['ok', 'Campo publicado.'];
    } elseif ($what === 'delete_field') {
        db()->prepare('DELETE FROM ' . t('game_fields') . ' WHERE id = ?')->execute([$target]);
        $_SESSION['flash'] = ['ok', 'Campo eliminado.'];
    } elseif ($what === 'toggle_admin' && $target !== (int)$me['id']) {
        $row = db()->prepare('SELECT is_admin FROM ' . t('users') . ' WHERE id = ?');
        $row->execute([$target]);
        if ($u = $row->fetch()) {
            db()->prepare('UPDATE ' . t('users') . ' SET is_admin = ? WHERE id = ?')->execute([$u['is_admin'] ? 0 : 1, $target]);
        }
    } elseif ($what === 'delete_user' && $target !== (int)$me['id']) {
        db()->prepare('DELETE FROM ' . t('users') . ' WHERE id = ?')->execute([$target]);
        $_SESSION['flash'] = ['ok', 'Usuario eliminado.'];
    }
    redirect('admin.php');
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$pending_fields = [];
try {
    $stmt = db()->prepare('SELECT f.*, u.username owner FROM ' . t('game_fields') . ' f JOIN ' . t('users') . ' u ON u.id = f.created_by WHERE f.is_approved = 0 ORDER BY f.created_at DESC');
    $stmt->execute();
    $pending_fields = $stmt->fetchAll();
} catch (PDOException $e) { $pending_fields = []; }

$stmt = db()->query('SELECT u.*, (SELECT COUNT(*) FROM ' . t('posts') . ' p WHERE p.user_id = u.id) posts FROM ' . t('users') . ' u ORDER BY u.created_at DESC');
$users = $stmt->fetchAll();

$stats = [
    'usuarios' => count($users),
    'campos aprobados' => (int)db()->query('SELECT COUNT(*) c FROM ' . t('game_fields') . ' WHERE is_approved = 1')->fetch()['c'],
    'campos pendientes' => count($pending_fields),
    'publicaciones' => (int)db()->query('SELECT COUNT(*) c FROM ' . t('posts'))->fetch()['c'],
    'historias activas' => (int)db()->query('SELECT COUNT(*) c FROM ' . t('stories') . ' WHERE (expires_at IS NULL OR expires_at > NOW())')->fetch()['c'],
];

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="stats-grid">
  <?php foreach ($stats as $k => $v): ?>
    <div class="stat-card card"><span class="num"><?= $v ?></span><span><?= $k ?></span></div>
  <?php endforeach; ?>
</div>

<h3 class="section-title">Campos pendientes de aprobación</h3>
<?php if (!$pending_fields): ?>
  <div class="card empty">No hay campos pendientes. Este apartado se rellena cuando los usuarios dan de alta campos.</div>
<?php endif; ?>
<?php foreach ($pending_fields as $f): ?>
  <div class="card admin-row">
    <div>
      <a href="field_view.php?id=<?= (int)$f['id'] ?>"><strong>🎯 <?= e($f['name']) ?></strong></a>
      <span class="muted">📍 <?= e($f['location'] ?: '?') ?> · por <?= e($f['owner']) ?></span>
    </div>
    <div class="row-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="what" value="approve_field"><input type="hidden" name="target" value="<?= (int)$f['id'] ?>">
        <button class="btn btn-small btn-ok">✅ Publicar</button></form>
      <form method="post" onsubmit="return confirm('¿Eliminar?')"><?= csrf_field() ?><input type="hidden" name="what" value="delete_field"><input type="hidden" name="target" value="<?= (int)$f['id'] ?>">
        <button class="btn btn-small btn-danger">🗑️</button></form>
    </div>
  </div>
<?php endforeach; ?>

<h3 class="section-title">Usuarios registrados</h3>
<div class="card">
  <table class="table">
    <thead><tr><th>Usuario</th><th>Correo</th><th>Alta</th><th>Publicaciones</th><th>Rol</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <a class="user-mini" href="profile.php?id=<?= (int)$u['id'] ?>">
              <img class="avatar sm" src="<?= avatar_src($u['avatar']) ?>" alt=""> <?= e($u['username']) ?>
            </a>
          </td>
          <td><?= e($u['email']) ?></td>
          <td><?= date('d/m/y', strtotime($u['created_at'])) ?></td>
          <td><?= (int)$u['posts'] ?></td>
          <td><?= $u['is_admin'] ? '<span class="admin-badge">Admin</span>' : 'Usuario' ?></td>
          <td class="row-actions">
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="what" value="toggle_admin"><input type="hidden" name="target" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-small btn-ghost"><?= $u['is_admin'] ? 'Quitar admin' : 'Hacer admin' ?></button></form>
              <form method="post" onsubmit="return confirm('¿Eliminar este usuario y todo su contenido?')"><?= csrf_field() ?><input type="hidden" name="what" value="delete_user"><input type="hidden" name="target" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-small btn-danger">Eliminar</button></form>
            <?php else: ?>
              <span class="muted">(tú)</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>