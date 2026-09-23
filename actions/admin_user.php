<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();
$me = current_user();

$id = (int)($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM ' . t('users') . ' WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect('admin.php');

$back = 'admin_user_edit.php?user=' . $id;
$what = $_POST['what'] ?? '';

if ($what === 'delete') {
    if ($id === (int)$me['id']) { $_SESSION['flash'] = ['error', 'No puedes eliminar tu propia cuenta.']; redirect('admin.php'); }
    db()->prepare('DELETE FROM ' . t('users') . ' WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['ok', 'Usuario eliminado.'];
    redirect('admin.php');
}

if ($what === 'save') {
    $username = preg_replace('/[^A-Za-z0-9_]/', '', trim($_POST['username'] ?? ''));
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $level = trim($_POST['experience_level'] ?? '');
    $style = trim($_POST['playing_style'] ?? '');
    $weapon = trim($_POST['primary_weapon'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $makeAdmin = !empty($_POST['is_admin']) ? 1 : 0;

    if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $_SESSION['flash'] = ['error', 'El nombre de usuario debe tener entre 3 y 50 caracteres.'];
        redirect($back);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = ['error', 'Correo electrónico no válido.'];
        redirect($back);
    }
    if ($newPass !== '' && mb_strlen($newPass) < 6) {
        $_SESSION['flash'] = ['error', 'La nueva contraseña debe tener al menos 6 caracteres.'];
        redirect($back);
    }

    /* Evitar duplicados de nombre de usuario / correo */
    $check = db()->prepare('SELECT id FROM ' . t('users') . ' WHERE (username = ? OR email = ?) AND id <> ?');
    $check->execute([$username, $email, $id]);
    if ($check->fetch()) {
        $_SESSION['flash'] = ['error', 'Ese nombre de usuario o correo ya pertenece a otra cuenta.'];
        redirect($back);
    }

    /* Protecciones del rol: no quitar el rol al último admin ni cambiarse a uno mismo. */
    if ((int)$user['is_admin'] === 1 && !$makeAdmin) {
        $adminCount = (int)db()->query('SELECT COUNT(*) FROM ' . t('users') . ' WHERE is_admin = 1')->fetchColumn();
        if ($adminCount <= 1) {
            $_SESSION['flash'] = ['error', 'Debe existir al menos un administrador. No puedes quitar el rol al último admin.'];
            redirect($back);
        }
    }
    if ($makeAdmin === 0 && $id === (int)$me['id']) {
        $_SESSION['flash'] = ['error', 'No puedes quitarte el rol de administrador a ti mismo.'];
        redirect($back);
    }

    $upd = db()->prepare('UPDATE ' . t('users') . ' SET username = ?, full_name = ?, email = ?, location = ?, experience_level = ?, playing_style = ?, primary_weapon = ?, is_admin = ? WHERE id = ?');
    $upd->execute([$username, $full_name ?: null, $email, $location ?: null, $level ?: null, $style ?: null, $weapon ?: null, $makeAdmin, $id]);

    if ($newPass !== '') {
        db()->prepare('UPDATE ' . t('users') . ' SET password = ? WHERE id = ?')->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
    }

    $_SESSION['flash'] = ['ok', 'Usuario actualizado.'];
    redirect('admin.php');
}

redirect('admin.php');