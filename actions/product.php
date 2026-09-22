<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();
$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $sort = (int)($_POST['sort_order'] ?? 0);
    if ($name === '') { $_SESSION['flash'] = ['error', 'El nombre del producto es obligatorio.']; redirect('store_admin.php'); }

    $image = null;
    if ($id) {
        $stmt = db()->prepare('SELECT image FROM ' . t('products') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $image = $row['image'] ?? null;
    }
    if (!empty($_FILES['image']['name'])) {
        $up = upload_file($_FILES['image'], 'products', 'image');
        if (!$up['ok']) { $_SESSION['flash'] = ['error', $up['error']]; redirect('store_admin.php'); }
        $image = $up['path'];
    }

    if ($id) {
        db()->prepare('UPDATE ' . t('products') . ' SET name=?, price=?, url=?, description=?, image=?, active=?, sort_order=? WHERE id=?')
            ->execute([$name, $price ?: null, $url ?: null, $desc ?: null, $image, $active, $sort, $id]);
        $_SESSION['flash'] = ['ok', 'Producto actualizado.'];
    } else {
        db()->prepare('INSERT INTO ' . t('products') . ' (name, price, url, description, image, active, sort_order) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $price ?: null, $url ?: null, $desc ?: null, $image, $active, $sort]);
        $_SESSION['flash'] = ['ok', 'Producto añadido a la tienda.'];
    }
    redirect('store_admin.php');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM ' . t('products') . ' WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['ok', 'Producto eliminado.'];
    redirect('store_admin.php');
}

redirect('store_admin.php');