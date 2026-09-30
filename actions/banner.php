<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $sort = (int)($_POST['sort_order'] ?? 0);

    if ($title === '') { $_SESSION['flash'] = ['error', 'El título del anuncio es obligatorio.']; redirect('banner_admin.php'); }
    if (!preg_match('#^https?://#i', $url)) {
        $_SESSION['flash'] = ['error', 'El enlace del anuncio debe ser una URL válida que empiece por http:// o https:// (así nunca abrirá una página en blanco).'];
        redirect('banner_admin.php');
    }
    $desc = mb_substr($desc, 0, 400);
    $source = mb_substr($source, 0, 120);

    $image = null;
    if ($id) {
        $stmt = db()->prepare('SELECT image FROM ' . t('ad_banners') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetch()['image'] ?? null;
    }
    if (!empty($_FILES['image']['name'])) {
        $up = upload_file($_FILES['image'], 'banners', 'image');
        if (!$up['ok']) { $_SESSION['flash'] = ['error', $up['error']]; redirect('banner_admin.php'); }
        $image = $up['path'];
    } elseif (!empty($_POST['image_imported'])) {
        /* Imagen detectada automáticamente desde la URL del anuncio:
         * puede ser una subida local en uploads/ o la URL remota original. */
        $imp = trim($_POST['image_imported']);
        if (strpos($imp, '..') === false && is_file(dirname(__DIR__) . '/' . $imp)) {
            $image = $imp;
        } elseif (preg_match('#^https?://#i', $imp) && strlen($imp) <= 255) {
            $image = $imp;
        }
    }

    if ($id) {
        db()->prepare('UPDATE ' . t('ad_banners') . ' SET title=?, description=?, url=?, source=?, image=?, active=?, sort_order=? WHERE id=?')
            ->execute([$title, $desc ?: null, $url, $source ?: null, $image, $active, $sort, $id]);
        $_SESSION['flash'] = ['ok', 'Anuncio actualizado.'];
    } else {
        db()->prepare('INSERT INTO ' . t('ad_banners') . ' (title, description, url, source, image, active, sort_order) VALUES (?,?,?,?,?,?,?)')
            ->execute([$title, $desc ?: null, $url, $source ?: null, $image, $active, $sort]);
        $_SESSION['flash'] = ['ok', 'Anuncio añadido: ya aparece en la parte alta del área de trabajo.'];
    }
    redirect('banner_admin.php');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM ' . t('ad_banners') . ' WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['ok', 'Anuncio eliminado.'];
    redirect('banner_admin.php');
}

redirect('banner_admin.php');
