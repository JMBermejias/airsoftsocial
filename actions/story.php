<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$me = current_user();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $content = trim($_POST['content'] ?? '');
    $image = null;
    $video = null;
    if (!empty($_FILES['image']['name'])) {
        $up = upload_file($_FILES['image'], 'stories', 'image');
        if (!$up['ok']) {
            $_SESSION['flash'] = ['error', $up['error']];
            redirect('stories.php');
        }
        $image = $up['path'];
    }
    if (!empty($_FILES['video']['name'])) {
        $upv = upload_file($_FILES['video'], 'stories', 'video');
        if (!$upv['ok']) {
            $_SESSION['flash'] = ['error', $upv['error']];
            redirect('stories.php');
        }
        $video = $upv['path'];
    }
    if ($content === '' && $image === null && $video === null) {
        $_SESSION['flash'] = ['error', 'Escribe algo o sube una imagen o un vídeo.'];
        redirect('stories.php');
    }
    /* Por defecto la historia es PERMANENTE; solo expira si se marca "24 h" */
    $expires = !empty($_POST['expires_24h']) ? 'DATE_ADD(NOW(), INTERVAL 24 HOUR)' : 'NULL';
    $stmt = db()->prepare('INSERT INTO ' . t('stories') . ' (user_id, content, image, video, expires_at) VALUES (?,?,?,?,' . $expires . ')');
    $stmt->execute([$me['id'], $content, $image, $video]);
    $_SESSION['flash'] = ['ok', !empty($_POST['expires_24h']) ? 'Historia publicada (durará 24 horas).' : 'Historia publicada (permanente).'];
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