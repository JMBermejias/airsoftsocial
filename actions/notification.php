<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
verify_csrf();
db()->prepare('UPDATE ' . t('notifications') . ' SET is_read = 1 WHERE user_id = ?')->execute([$_SESSION['user_id']]);
redirect($_POST['back'] ?? 'feed.php');