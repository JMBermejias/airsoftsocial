<?php
$page_title = 'Editar usuario';
require_once __DIR__ . '/includes/functions.php';
require_admin();
$me = current_user();

$id = (int)($_GET['user'] ?? 0);
$stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect('admin.php');

$isSelf = (int)$user['id'] === (int)$me['id'];
$adminCount = (int)db()->query('SELECT COUNT(*) FROM ' . t('users') . ' WHERE is_admin = 1')->fetchColumn();
$lastAdmin = (int)$user['is_admin'] === 1 && $adminCount <= 1;
$cantChangeRole = $isSelf || $lastAdmin;

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="toolbar">
  <a class="btn btn-ghost" href="admin.php">← Volver al panel</a>
</div>

<section class="card" style="max-width:640px">
  <h3>Editar usuario <span class="muted">(<?= e($user['username']) ?>)</span></h3>
  <p class="muted"><?= rank_badge($user) ?> · <?= days_registered($user) ?> días de antigüedad · alta el <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>

  <form method="post" action="actions/admin_user.php">
    <?= csrf_field() ?>
    <input type="hidden" name="what" value="save">
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
    <div class="form-row">
      <label>Nombre de usuario
        <input type="text" name="username" required minlength="3" maxlength="50" value="<?= e($user['username']) ?>">
      </label>
      <label>Nombre completo
        <input type="text" name="full_name" maxlength="100" value="<?= e($user['full_name'] ?? '') ?>">
      </label>
    </div>
    <label>Correo electrónico
      <input type="email" name="email" required value="<?= e($user['email']) ?>">
    </label>
    <div class="form-row">
      <label>Ubicación
        <input type="text" name="location" maxlength="150" value="<?= e($user['location'] ?? '') ?>">
      </label>
      <label>Experiencia
        <input type="text" name="experience_level" maxlength="60" value="<?= e($user['experience_level'] ?? '') ?>">
      </label>
    </div>
    <div class="form-row">
      <label>Estilo de juego
        <input type="text" name="playing_style" maxlength="60" value="<?= e($user['playing_style'] ?? '') ?>">
      </label>
      <label>Arma principal
        <input type="text" name="primary_weapon" maxlength="100" value="<?= e($user['primary_weapon'] ?? '') ?>">
      </label>
    </div>
    <label>Restablecer contraseña <span class="muted">(déjala vacía para no cambiarla)</span>
      <input type="password" name="new_password" placeholder="•••••••">
    </label>

    <label class="tool" style="margin-top:14px">
      <input type="checkbox" name="is_admin" value="1" <?= $user['is_admin'] ? 'checked' : '' ?> <?= $cantChangeRole ? 'disabled onclick="return false"' : '' ?>>
      🎖️ <span><?= ($user['is_admin'] ? 'Es administrador' : 'Hacer administrador') ?><?= $isSelf ? ' (tu cuenta)' : '' ?><?= $lastAdmin ? ' (último administrador: no se puede quitar)' : '' ?></span>
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
      <a class="btn btn-ghost" href="admin.php">Cancelar</a>
    </div>
  </form>
  <?php if (!$isSelf): ?>
    <form method="post" action="actions/admin_user.php" onsubmit="return confirm('¿Eliminar este usuario y todo su contenido (publicaciones, historias, comentarios)?')">
      <?= csrf_field() ?>
      <input type="hidden" name="what" value="delete">
      <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
      <button class="btn btn-danger" type="submit">🗑️ Eliminar usuario</button>
    </form>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>