<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$me = current_user();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $content = trim($_POST['content'] ?? '');
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $up = upload_file($_FILES['image'], 'stories', 'image');
        if (!$up['ok']) {
            $_SESSION['flash'] = ['error', $up['error']];
            redirect('stories.php');
        }
        $image = $up['path'];
    }
    if ($content === '' && $image === null) {
        $_SESSION['flash'] = ['error', 'Escribe algo o sube una imagen.'];
        redirect('stories.php');
    }
    $stmt = db()->prepare('INSERT INTO ' . t('stories') . ' (user_id, content, image, expires_at) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))');
    $stmt->execute([$me['id'], $content, $image]);
    $_SESSION['flash'] = ['ok', 'Historia publicada (durará 24 horas).'];
    redirect('feed.php');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM ' . t('stories') . ' WHERE id = ?');
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if ($s && ((int)$s['user_id'] === (int)$me['id'] || is_admin())) {
        db()->prepare('DELETE FROM ' . t('stories') . ' WHERE id = ?')->execute([$id]);
    }
    redirect('stories.php');
}

redirect('stories.php');