<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $me = current_user();
    $username = preg_replace('/[^A-Za-z0-9_]/', '', trim($_POST['username'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $exp = trim($_POST['experience_level'] ?? '');
    $style = trim($_POST['playing_style'] ?? '');
    $weapon = trim($_POST['primary_weapon'] ?? '');
    $error = '';

    if (mb_strlen($username) < 3) $error = 'El nombre de usuario debe tener al menos 3 caracteres.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Correo electrónico no válido.';

    if (!$error) {
        $dup = db()->prepare('SELECT id FROM ' . t('users') . ' WHERE (username = ? OR email = ?) AND id <> ?');
        $dup->execute([$username, $email, $me['id']]);
        if ($dup->fetch()) $error = 'Ese nombre o correo ya lo usa otra cuenta.';
    }

    $avatar = $me['avatar'];
    if (!$error && !empty($_FILES['avatar']['name'])) {
        $up = upload_file($_FILES['avatar'], 'avatars', 'image');
        if (!$up['ok']) $error = $up['error'];
        else $avatar = $up['path'];
    }

    if (!$error && !empty($_POST['new_password']) && !empty($_POST['new_password2'])) {
        if (mb_strlen($_POST['new_password']) < 6) $error = 'La contraseña debe tener al menos 6 caracteres.';
        elseif ($_POST['new_password'] !== $_POST['new_password2']) $error = 'Las contraseñas no coinciden.';
    }

    if ($error) {
        $_SESSION['flash'] = ['error', $error];
    } else {
        $sql = 'UPDATE ' . t('users') . ' SET username=?, email=?, full_name=?, location=?, bio=?, experience_level=?, playing_style=?, primary_weapon=?, avatar=?';
        $params = [$username, $email, $full_name ?: null, $location ?: null, $bio ?: null, $exp ?: null, $style ?: null, $weapon ?: null, $avatar];
        if (!empty($_POST['new_password']) && !empty($_POST['new_password2']) && $_POST['new_password'] === $_POST['new_password2']) {
            $sql .= ', password=?';
            $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $me['id'];
        db()->prepare($sql)->execute($params);
        $_SESSION['flash'] = ['ok', 'Perfil actualizado.'];
        redirect('profile.php?id=' . $me['id']);
    }
}

$me = current_user();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$opts_exp = ['Sin experiencia', 'Novato', 'Intermedio', 'Avanzado', 'Veterano', 'Ex-militar / profesional'];
$opts_style = ['Asalto', 'Sniper', 'Apoyo / LMG', 'Punto de combate', 'Reconocimiento', 'Combate urbano', 'Mixto'];
$page_title = 'Editar mi perfil';
require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="avatar-edit">
    <img class="avatar xl" id="avatar-preview" src="<?= avatar_src($me['avatar']) ?>" alt="avatar">
    <label class="file-picker"><input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)"> 📷 Cambiar imagen de usuario</label>
  </div>

  <div class="form-row">
    <label>Nombre de usuario
      <input type="text" name="username" value="<?= e($me['username']) ?>" required minlength="3" maxlength="50">
    </label>
    <label>Correo electrónico
      <input type="email" name="email" value="<?= e($me['email']) ?>" required>
    </label>
  </div>

  <div class="form-row">
    <label>Nombre completo / alias
      <input type="text" name="full_name" value="<?= e($me['full_name'] ?? '') ?>" placeholder="Tu nombre o apodo">
    </label>
    <label>Ubicación
      <input type="text" name="location" value="<?= e($me['location'] ?? '') ?>" placeholder="Ciudad, región…">
    </label>
  </div>

  <div class="form-row">
    <label>Nivel de experiencia
      <select name="experience_level">
        <?php foreach ($opts_exp as $o): ?>
          <option value="<?= e($o) ?>" <?= $me['experience_level'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Estilo de juego
      <select name="playing_style">
        <option value="" <?= empty($me['playing_style']) ? 'selected' : '' ?>>Sin especificar</option>
        <?php foreach ($opts_style as $o): ?>
          <option value="<?= e($o) ?>" <?= $me['playing_style'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <label>Arma principal
    <input type="text" name="primary_weapon" value="<?= e($me['primary_weapon'] ?? '') ?>" placeholder="Ej: Marui VSR-10, AEG M4,…">
  </label>

  <label>Sobre mí (biografía)
    <textarea name="bio" rows="4" placeholder="Cuéntanos tu experiencia en el airsoft…"><?= e($me['bio'] ?? '') ?></textarea>
  </label>

  <div class="form-row">
    <label>Nueva contraseña (opcional)
      <input type="password" name="new_password" placeholder="déjala vacía para no cambiar">
    </label>
    <label>Repite la nueva contraseña
      <input type="password" name="new_password2" placeholder="repite la contraseña">
    </label>
  </div>

  <div class="form-actions">
    <a href="profile.php?id=<?= (int)$me['id'] ?>" class="btn btn-ghost">Cancelar</a>
    <button class="btn btn-primary" type="submit">Guardar perfil</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>