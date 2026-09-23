<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();

$meta = $_POST['meta'] ?? [];
$sort = $_POST['sort'] ?? [];
$enabled = $_POST['enabled'] ?? '';
$enabledKeys = is_array($enabled) ? array_flip($enabled) : [];

$stmtList = db()->query('SELECT * FROM ' . t('payment_methods') . ' ORDER BY sort_order ASC, id ASC');
$upd = db()->prepare('UPDATE ' . t('payment_methods') . ' SET meta = ?, is_enabled = ?, sort_order = ? WHERE method_key = ?');
foreach ($stmtList->fetchAll() as $m) {
    $k = $m['method_key'];
    $upd->execute([
        trim((string)($meta[$k] ?? '')),
        isset($enabledKeys[$k]) ? 1 : 0,
        (int)($sort[$k] ?? $m['sort_order']),
        $k,
    ]);
}

$_SESSION['flash'] = ['ok', 'Formas de pago actualizadas. Se muestran en la ficha de cada producto.'];
redirect('store_admin.php');