<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user();

$id = (int)($_GET['id'] ?? 0);
$f = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM ' . t('game_fields') . ' WHERE id = ?');
    $stmt->execute([$id]);
    $f = $stmt->fetch();
    if (!$f) redirect('fields.php');
    $canEdit = is_admin() || (int)$f['created_by'] === (int)$me['id'];
    if (!$canEdit) redirect('fields.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $game_type = trim($_POST['game_type'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = trim($_POST['price'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['game_requirements'] ?? '');

    if ($name === '') {
        $error = 'El nombre del campo es obligatorio.';
    } else {
        $image = $f['image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $up = upload_file($_FILES['image'], 'fields', 'image');
            if (!$up['ok']) $error = $up['error'];
            else $image = $up['path'];
        }
        if (!$error) {
            if ($id) {
                db()->prepare('UPDATE ' . t('game_fields') . ' SET name=?, location=?, game_type=?, capacity=?, price=?, contact=?, description=?, game_requirements=?, image=? WHERE id=? AND (created_by=? OR ?)')
                    ->execute([$name, $location ?: null, $game_type ?: null, $capacity ?: null, $price ?: null, $contact ?: null, $description ?: null, $requirements ?: null, $image, $id, $me['id'], $me['is_admin'] ? 1 : 0]);
                $_SESSION['flash'] = ['ok', 'Campo actualizado.'];
                redirect('field_view.php?id=' . $id);
            } else {
                $approved = is_admin() ? 1 : 0;
                db()->prepare('INSERT INTO ' . t('game_fields') . ' (name, location, game_type, capacity, price, contact, description, game_requirements, image, created_by, is_approved) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$name, $location ?: null, $game_type ?: null, $capacity ?: null, $price ?: null, $contact ?: null, $description ?: null, $requirements ?: null, $image, $me['id'], $approved]);
                $nid = (int)db()->lastInsertId();
                $_SESSION['flash'] = ['ok', $approved ? 'Campo publicado correctamente.' : 'Campo enviado. Un administrador lo aprobará antes de publicarlo.'];
                redirect('field_view.php?id=' . $nid);
            }
        }
    }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$page_title = $id ? 'Editar campo' : 'Dar de alta un campo';
$page_subtitle = 'Completa los datos del campo de juego y sus requisitos';
require_once __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card form-main">
  <?= csrf_field() ?>
  <div class="form-row">
    <label>Nombre del campo *
      <input type="text" name="name" required value="<?= e($f['name'] ?? '') ?>" placeholder="Ej: Bosque Los Pinares">
    </label>
    <label>Ubicación
      <input type="text" name="location" value="<?= e($f['location'] ?? '') ?>" placeholder="Ciudad / zona">
    </label>
  </div>

  <div class="form-row">
    <label>Tipo de juego
      <input type="text" name="game_type" value="<?= e($f['game_type'] ?? '') ?>" placeholder="Ej: bosque, CQB, urbano, mixto…">
    </label>
    <label>Capacidad (jugadores)
      <input type="number" name="capacity" min="1" value="<?= (int)($f['capacity'] ?? 0) ?>" placeholder="Ej: 20">
    </label>
  </div>

  <div class="form-row">
    <label>Precio
      <input type="text" name="price" value="<?= e($f['price'] ?? '') ?>" placeholder="Ej: 15 €/persona">
    </label>
    <label>Contacto
      <input type="text" name="contact" value="<?= e($f['contact'] ?? '') ?>" placeholder="Teléfono, web o email">
    </label>
  </div>

  <label>Descripción del campo
    <textarea name="description" rows="4" placeholder="Tamaño, vegetación, instalaciones, estructura…"><?= e($f['description'] ?? '') ?></textarea>
  </label>

  <label>Requisitos de juego
    <textarea name="game_requirements" rows="4" placeholder="Protección facial obligatoria, límite de fps, edad mínima, seguros de réplica…"><?= e($f['game_requirements'] ?? '') ?></textarea>
  </label>

<div class="field-label">Imagen del campo</div>
  <label class="file-picker"><input type="file" name="image" accept="image/*"> 📷 Subir imagen</label>
  <?php if (!empty($f['image'])): ?><img class="thumb" src="<?= e($f['image']) ?>" alt="imagen actual"><?php endif; ?>

  <div class="form-actions">
    <a href="fields.php" class="btn btn-ghost">Cancelar</a>
    <button class="btn btn-primary" type="submit"><?= $id ? 'Guardar cambios' : 'Dar de alta campo' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>