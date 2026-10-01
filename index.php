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
        $u = find_user_by_login($id);
        if ($u && password_verify($pass, $u['password'])) {
            login_user($u);
            redirect('feed.php');
        }
        /* Dos mensajes distintos, porque son dos problemas distintos y el
         * genérico hacía imposible saber cuál era: con «no existe la cuenta»
         * se sabe que el nombre de usuario está mal escrito. */
        $error = $u
            ? 'La contraseña no es correcta. Si no la recuerdas, pídele a un administrador que te la cambie.'
            : 'No hay ninguna cuenta con «' . $id . '». Entra con tu nombre de usuario o con tu correo electrónico.';

        /* Si el nombre sin limpiar no coincide con nadie, se prueba con la
         * forma "limpia" (sin puntos ni guiones) por si esa es la que se
         * escribió al registrarse. Si aparece, se dice cuál es el nombre real:
         * así se entra y además la persona aprende cómo se guardó. */
        if (!$u) {
            $plain = preg_replace('/[^A-Za-z0-9_]/', '', $id);
            if ($plain !== '' && $plain !== $id) {
                $s = db()->prepare('SELECT username FROM ' . t('users') . ' WHERE username = ? LIMIT 1');
                $s->execute([$plain]);
                if ($s->fetch()) {
                    $error = 'El usuario se guardó como «' . $plain . '» (los puntos y guiones se quitan al registrarse). '
                           . 'Entra con ese nombre, con tu correo, o con «' . $id . '» a partir de ahora.';
                }
            }
        }
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
                $makeAdmin = !empty($_POST['make_admin']) ? 1 : 0;
                $admins = (int)db()->query('SELECT COUNT(*) FROM ' . t('users') . ' WHERE is_admin = 1')->fetchColumn();
                if ($makeAdmin && $admins > 0) {
                    $error = 'Ya existe un administrador. No puedes marcarte como tal.';
                } else {
                    $ins = db()->prepare('INSERT INTO ' . t('users') . ' (username, email, password, is_admin) VALUES (?,?,?,?)');
                    $ins->execute([$username, $email, password_hash($pass1, PASSWORD_DEFAULT), $makeAdmin ? 1 : 0]);
                    $uid = (int)db()->lastInsertId();
                    $fetch = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
                    $fetch->execute([$uid]);
                    login_user($fetch->fetch());
                    redirect('feed.php');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Airsoft Social: red social para la comunidad airsoft. Noticias, historias, amigos, campos de juego y tienda online.">
<meta name="theme-color" content="#3a4b3b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" type="image/png" sizes="192x192" href="icon.php?src=icon-192.png">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="assets/img/icons/icon-512.png">
<link rel="apple-touch-icon" href="assets/img/icons/icon-180.png">
<title><?= e(APP_NAME) ?> · Iniciar sesión</title>
<link rel="stylesheet" href="assets/css/style.css?v=25">
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
      <label>Nombre de usuario o correo
        <input type="text" name="id" required autofocus autocomplete="username"
               autocapitalize="none" autocorrect="off" spellcheck="false"
               placeholder="sniper_24  o  correo@ejemplo.com">
      </label>
      <label>Contraseña
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </label>
      <button class="btn btn-primary btn-block" type="submit">Entrar</button>
      <p class="check-hint" style="margin:2px 0 0;text-align:center">
        Con el mismo nombre con el que te registraste, o con tu correo.
      </p>
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
      <?php if (admin_count() === 0): ?>
        <label class="check-hint">
          <input type="checkbox" name="make_admin" value="1">
          🎖️ <strong>Quiero ser el Administrador</strong> de la red (solo disponible para el primer usuario registrado).
        </label>
      <?php endif; ?>
      <button class="btn btn-primary btn-block" type="submit">Crear cuenta</button>
    </form>
  </div>
  <button class="btn btn-ghost btn-block pwa-install" onclick="pwaInstall()" style="margin-top:12px">⬇️ Instalar Airsoft Social en el escritorio / móvil</button>
</div>
<script src="assets/js/app.js?v=14"></script>
<script>
function switchAuth(t){
  document.getElementById('form-login').classList.toggle('hidden', t!=='login');
  document.getElementById('form-register').classList.toggle('hidden', t!=='register');
  document.querySelectorAll('.auth-tabs .tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===t));
}
/* Registro del service worker con ruta absoluta: si la app está en una
 * subcarpeta, la ruta relativa se resuelve contra la página actual
 * y el registro falla, con lo que Chrome no ofrece instalar la app. */
(function () {
  if (!('serviceWorker' in navigator)) return;
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
  var dir = location.pathname.replace(/[^\/]*$/, '');
  /* Ámbito explícito: si la app está en una subcarpeta, sin esto Chrome no
     controla el manifest ni las páginas de la raíz y la instalación falla. */
  navigator.serviceWorker.register(dir + 'service-worker.js', { scope: dir })
    .then(function () { console.log('[PWA] Service worker registrado en', dir); })
    .catch(function (err) {
      console.error('[PWA] No se pudo registrar el service worker:', err, dir);
    });
})();
</script>
</body>
</html>