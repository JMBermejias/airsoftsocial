<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
verify_csrf();
$me = current_user();

$action = $_POST['action'] ?? '';

/* ---------- Crear publicación ---------- */
if ($action === 'create') {
    $content = trim($_POST['content'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $is_general = (is_admin() && isset($_POST['is_general'])) ? 1 : 0;

    $file = null;
    $file_type = null;
    if (!empty($_FILES['file']['name'])) {
        $kind = 'any';
        if (($_FILES['file']['type'] ?? '') === 'application/pdf') {
            $kind = 'pdf';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
            if (in_array($mime, MIME_IMG, true)) $kind = 'image';
        }
        if ($kind === 'any') {
            $_SESSION['flash'] = ['error', 'Solo se permiten imágenes (JPG, PNG, GIF, WEBP) o PDF.'];
            redirect('feed.php');
        }
        $up = upload_file($_FILES['file'], 'posts', $kind);
        if (!$up['ok']) {
            $_SESSION['flash'] = ['error', $up['error']];
            redirect('feed.php');
        }
        $file = $up['path'];
        $file_type = ($kind === 'pdf') ? 'pdf' : 'image';
    }

    if ($content === '' && $file === null) {
        $_SESSION['flash'] = ['error', 'Escribe algo o añade un archivo.'];
        redirect('feed.php');
    }

    $stmt = db()->prepare('INSERT INTO ' . t('posts') . ' (user_id, content, file, file_type, tags, is_general) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$me['id'], $content, $file, $file_type, $tags ?: null, $is_general]);
    $_SESSION['flash'] = ['ok', 'Publicación creada.'];
    redirect('feed.php');
}

/* ---------- Eliminar publicación ---------- */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM ' . t('posts') . ' WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if ($post && ($post['user_id'] == $me['id'] || is_admin())) {
        db()->prepare('DELETE FROM ' . t('posts') . ' WHERE id = ?')->execute([$id]);
        $_SESSION['flash'] = ['ok', 'Publicación eliminada.'];
    }
    redirect('feed.php');
}

/* ---------- Dar / quitar like ---------- */
if ($action === 'like') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM ' . t('posts') . ' WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if ($post) {
        $lk = db()->prepare('SELECT id FROM ' . t('post_likes') . ' WHERE post_id = ? AND user_id = ?');
        $lk->execute([$id, $me['id']]);
        if ($lk->fetch()) {
            db()->prepare('DELETE FROM ' . t('post_likes') . ' WHERE post_id = ? AND user_id = ?')->execute([$id, $me['id']]);
        } else {
            db()->prepare('INSERT INTO ' . t('post_likes') . ' (post_id, user_id) VALUES (?,?)')->execute([$id, $me['id']]);
            if ((int)$post['user_id'] !== (int)$me['id']) {
                notify((int)$post['user_id'], $me['id'], 'like', 'Le ha gustado tu publicación', 'feed.php');
            }
        }
    }
    redirect('feed.php');
}

/* ---------- Comentar ---------- */
if ($action === 'comment') {
    $id = (int)($_POST['id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($id > 0 && $comment !== '') {
        $stmt = db()->prepare('SELECT user_id FROM ' . t('posts') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if ($p) {
            db()->prepare('INSERT INTO ' . t('post_comments') . ' (post_id, user_id, comment) VALUES (?,?,?)')->execute([$id, $me['id'], $comment]);
            if ((int)$p['user_id'] !== (int)$me['id']) {
                notify((int)$p['user_id'], $me['id'], 'comment', 'Ha comentado tu publicación', 'feed.php');
            }
        }
    }
    redirect('feed.php');
}

redirect('feed.php');