<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged()) redirect('feed.php');

$error = '';
$form = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] === 'register' ? 'register' : 'login';

    if ($form === 'login') {
        $id = trim($_POST['id'] ?? '');
        $pass = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$id, $id]);
        $u = $stmt->fetch();
        if ($u && password_verify($pass, $u['password'])) {
            login_user($u);
            redirect('feed.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        $username = preg_replace('/[^A-Za-z0-9_]/', '', trim($_POST['username'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $pass1 = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $error = 'El nombre de usuario debe tener entre 3 y 50 caracteres (letras, números, _).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Introduce un correo electrónico válido.';
        } elseif (mb_strlen($pass1) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $s = db()->prepare('SELECT id FROM ' . t('users') . ' WHERE username = ? OR email = ?');
            $s->execute([$username, $email]);
            if ($s->fetch()) {
                $error = 'Ese nombre de usuario o correo ya está registrado.';
            } else {
                $ins = db()->prepare('INSERT INTO ' . t('users') . ' (username, email, password) VALUES (?,?,?)');
                $ins->execute([$username, $email, password_hash($pass1, PASSWORD_DEFAULT)]);
                $uid = (int)db()->lastInsertId();
                $fetch = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
                $fetch->execute([$uid]);
                login_user($fetch->fetch());
                redirect('feed.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Social Airsoft: red social para la comunidad airsoft. Noticias, historias, amigos, campos de juego y tienda online.">
<meta name="theme-color" content="#3a4b3b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/img/icons/icon-180.png">
<title><?= e(APP_NAME) ?> · Iniciar sesión</title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="auth-body">
<div class="auth-wrap">
  <div class="auth-hero">
    <span class="hero-logo">🎯</span>
    <h1><?= e(APP_NAME) ?></h1>
    <p>La red social del airsoft: noticias, amigos, campos de juego, historias y tu tienda online.</p>
  </div>

  <div class="auth-card">
    <div class="auth-tabs">
      <button class="tab active" data-tab="login" onclick="switchAuth('login')">Entrar</button>
      <button class="tab" data-tab="register" onclick="switchAuth('register')">Registrarse</button>
    </div>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="index.php" id="form-login" class="auth-form">
      <input type="hidden" name="form" value="login">
      <label>Usuario o correo
        <input type="text" name="id" required autofocus placeholder="usuario@correo.com">
      </label>
      <label>Contraseña
        <input type="password" name="password" required placeholder="••••••••">
      </label>
      <button class="btn btn-primary btn-block" type="submit">Entrar</button>
    </form>

    <form method="post" action="index.php" id="form-register" class="auth-form hidden">
      <input type="hidden" name="form" value="register">
      <div class="form-row">
        <label>Nombre de usuario
          <input type="text" name="username" required minlength="3" maxlength="50" placeholder="ej: sniper_24">
        </label>
        <label>Correo electrónico
          <input type="email" name="email" required placeholder="tu@correo.com">
        </label>
      </div>
      <div class="form-row">
        <label>Contraseña
          <input type="password" name="password" required minlength="6" placeholder="mínimo 6 caracteres">
        </label>
        <label>Repite la contraseña
          <input type="password" name="password2" required placeholder="repite la contraseña">
        </label>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Crear cuenta</button>
    </form>
  </div>
</div>
<script>
function switchAuth(t){
  document.getElementById('form-login').classList.toggle('hidden', t!=='login');
  document.getElementById('form-register').classList.toggle('hidden', t!=='register');
  document.querySelectorAll('.auth-tabs .tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===t));
}
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
  navigator.serviceWorker.register('service-worker.js').catch(function(){});
}
</script>
</body>
</html>