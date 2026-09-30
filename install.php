<?php
/**
 * INSTALADOR DE AIRSOFT SOCIAL
 * --------------------------------------------
 * 1) Sube todos los archivos a tu hosting.
 * 2) Crea una base de datos MySQL en tu hosting.
 * 3) Abre https://tu-dominio.com/install.php y rellena los datos.
 * 4) Al terminar, BORRA este archivo por seguridad.
 */
require_once __DIR__ . '/includes/functions.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $admin = trim($_POST['admin_user'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';

    $errs = [];
    if ($name === '') $errs[] = 'El nombre de la base de datos es obligatorio.';
    if (mb_strlen($admin) < 3) $errs[] = 'El usuario administrador debe tener al menos 3 caracteres.';
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Correo del administrador no válido.';
    if (mb_strlen($admin_pass) < 6) $errs[] = 'La contraseña del administrador debe tener al menos 6 caracteres.';

    /* Probar conexión y ejecutar esquema */
    $pdo = null;
    if (!$errs) {
        try {
            $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4', $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $errs[] = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
        }
    }

    if (!$errs) {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                $errs[] = 'Error al crear las tablas: ' . $e->getMessage();
            }
        }
    }

    if (!$errs) {
        /* Migraciones de esquema (idempotentes) */
        run_migrations($pdo);
    }

    if (!$errs) {
        /* Crear el administrador */
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, is_admin) VALUES (?,?,?,?,1)');
            $stmt->execute([$admin, $admin_email, password_hash($admin_pass, PASSWORD_DEFAULT), 'Administrador']);
        } catch (PDOException $e) {
            /* administrador ya creado previamente */
        }

        /* Escribir config.php */
        $cfg = "<?php\n"
            . "/**\n"
            . " * CONFIGURACIÓN GENERADA POR EL INSTALADOR\n"
            . " */\n"
            . "define('DB_HOST', " . var_export($host, true) . ");\n"
            . "define('DB_NAME', " . var_export($name, true) . ");\n"
            . "define('DB_USER', " . var_export($user, true) . ");\n"
            . "define('DB_PASS', " . var_export($pass, true) . ");\n"
            . "define('DB_PREFIX', '');\n"
            . "define('APP_NAME', 'Airsoft Social');\n"
            . "date_default_timezone_set('Europe/Madrid');\n";
        $wrote = @file_put_contents(__DIR__ . '/../config.php', $cfg);
        if ($wrote === false) {
            $errs[] = 'No se pudo escribir el archivo config.php en la raíz de la web. Exporta el siguiente contenido y súbelo manualmente como config.php (File Manager -> Create File):';
            $errs['show_cfg'] = $cfg;
        } else {
            $_SESSION['install_done'] = true;
            redirect('install.php?step=2');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalación · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="auth-body">
<div class="auth-wrap install">
  <div class="auth-hero">
    <span class="hero-logo">🎯</span>
    <h1><?= e(APP_NAME) ?></h1>
    <p>Instalación del sitio.</p>
  </div>

  <?php if ($step === 2): ?>
    <div class="auth-card">
      <h3>✅ ¡Instalación completada!</h3>
      <p>Tu red social <strong><?= e(APP_NAME) ?></strong> está lista.</p>
      <ul>
        <li>Entra en <a href="index.php">index.php</a> e inicia sesión con tu cuenta de administrador.</li>
        <li>Desde la tienda podrás gestionar tu <strong>tienda online</strong> (aparece siempre a la derecha).</li>
        <li>Marca la casilla <em>Noticia general</em> al publicar para que una noticia llegue a todos los usuarios.</li>
        <li><strong class="danger">BORRA install.php</strong> de tu servidor por seguridad.</li>
      </ul>
      <a class="btn btn-primary btn-block" href="index.php">Ir a la red social →</a>
    </div>
  <?php else: ?>
    <div class="auth-card">
      <h3>Configuración inicial</h3>
      <?php if (!empty($errs)): ?>
        <?php foreach ($errs as $ek => $er): ?>
          <?php if ($ek === 'show_cfg'): ?>
            <div class="alert error">
              <strong>Contenido para config.php:</strong>
              <textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px;margin-top:8px;"><?= e($er) ?></textarea>
            </div>
          <?php else: ?>
            <div class="alert error"><?= e($er) ?></div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" action="install.php?step=1">
        <h4 class="form-section">Base de datos (los datos de tu hosting)</h4>
        <div class="form-row">
          <label>Servidor<input type="text" name="db_host" value="localhost" required></label>
          <label>Nombre de la base de datos<input type="text" name="db_name" required placeholder="nombretu_bd"></label>
        </div>
        <div class="form-row">
          <label>Usuario de la BD<input type="text" name="db_user" required></label>
          <label>Contraseña de la BD<input type="password" name="db_pass"></label>
        </div>

        <h4 class="form-section">Cuenta de administrador (tú)</h4>
        <div class="form-row">
          <label>Usuario<input type="text" name="admin_user" required minlength="3"></label>
          <label>Correo<input type="email" name="admin_email" required></label>
        </div>
        <label>Contraseña<input type="password" name="admin_pass" required minlength="6"></label>

        <button class="btn btn-primary btn-block" type="submit">Instalar</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>