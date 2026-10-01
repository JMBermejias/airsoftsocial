<?php
require_once __DIR__ . '/functions.php';

if (!is_logged()) redirect('index.php');
$me = current_user();
$unread = unread_notifs();
$pending = unread_requests();
$notifs = [];
if ($unread > 0) {
    $s = db()->prepare('SELECT n.*, u.username actor FROM ' . t('notifications') . ' n LEFT JOIN ' . t('users') . " u ON u.id = n.actor_id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 15");
    $s->execute([$me['id']]);
    $notifs = $s->fetchAll();
}

/* Auto-actualización: avisa a los admins si hay release más reciente */
$updateBanner = null;
if (is_admin()) {
    update_notify_admins();
    $upd = update_available();
    if ($upd) $updateBanner = $upd;
}

/* Autoparchado: si la base de datos se quedó atrás (código subido a mano o
 * actualización fallida), se arregla solo. Barato y acotado a una vez cada
 * pocas horas y solo para administradores: nunca en cada visita. */
$schemaIssues = ensure_schema();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Airsoft Social: red social para la comunidad airsoft. Noticias, historias, amigos, campos de juego y tienda online.">
<meta name="theme-color" content="#3a4b3b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/img/icons/icon-180.png">
<title><?= !empty($page_title) ? e($page_title) . ' · ' : '' ?><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=21">
</head>
<body>
<div class="layout">

  <!-- ===================== SIDEBAR IZQUIERDO: DASHBOARD DE CONTROL ===================== -->
  <aside class="sidebar left-sidebar">
    <div class="brand">
      <span class="brand-logo">🎯</span>
      <div>
        <h1><?= e(APP_NAME) ?></h1>
        <small>Red social airsoft</small>
      </div>
    </div>

    <div class="miniprofile">
      <a href="profile.php?id=<?= (int)$me['id'] ?>">
        <img class="avatar" src="<?= avatar_src($me['avatar']) ?>" alt="avatar">
      </a>
      <div class="miniprofile-meta">
        <a class="myname" href="profile.php?id=<?= (int)$me['id'] ?>"><?= e($me['username']) ?></a>
        <?= rank_badge($me) ?>
        <?php if (is_admin()): ?><span class="admin-badge">Administrador</span><?php endif; ?>
      </div>
      <a href="logout.php" class="logout" title="Cerrar sesión">✕</a>
    </div>

    <nav class="main-nav">
      <a href="feed.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'feed.php' ? 'active' : '' ?>">
        <span class="nav-ico">📰</span> Noticias
      </a>
      <a href="stories.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'stories.php' ? 'active' : '' ?>">
        <span class="nav-ico">📖</span> Historias
      </a>
      <a href="friends.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'friends.php' ? 'active' : '' ?>">
        <span class="nav-ico">🤝</span> Amigos
        <?php if ($pending > 0): ?><span class="badge"><?= $pending ?></span><?php endif; ?>
      </a>
      <a href="fields.php" class="nav-item <?= strpos(basename($_SERVER['PHP_SELF']), 'field') === 0 ? 'active' : '' ?>">
        <span class="nav-ico">🗺️</span> Campos de juego
      </a>
      <a href="store.php" class="nav-item <?= strpos(basename($_SERVER['PHP_SELF']), 'store') === 0 ? 'active' : '' ?>">
        <span class="nav-ico">🛒</span> Tienda
      </a>
      <a href="profile_edit.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'profile_edit.php' ? 'active' : '' ?>">
        <span class="nav-ico">⚙️</span> Mi perfil
      </a>
      <?php if (is_admin()): ?>
      <a href="admin.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'active' : '' ?>">
        <span class="nav-ico">🛠️</span> Panel admin
      </a>
      <a href="banner_admin.php" class="nav-item <?= strpos(basename($_SERVER['PHP_SELF']), 'banner') === 0 ? 'active' : '' ?>">
        <span class="nav-ico">📢</span> Anuncios
      </a>
      <a href="updates.php" class="nav-item <?= strpos(basename($_SERVER['PHP_SELF']), 'updates') === 0 ? 'active' : '' ?>">
        <span class="nav-ico">⬆️</span> Actualizar app
        <?php if ($updateBanner): ?><span class="badge"><?= e($updateBanner['latest']) ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
    </nav>

    <!-- Inicio y salida ARRIBA, justo debajo del menú, para no tener que bajar
         hasta el pie del panel. Antes solo había un "Salir" suelto en el menú y
         los dos botones buenos vivían abajo, en .sidebar-foot. -->
    <div class="sidebar-quick">
      <a href="feed.php" class="quick-item">
        <span class="nav-ico">🏠</span> Volver al inicio
      </a>
      <!-- onclick=false para que sea un <a> de verdad (si no, al estar
           instalada en el escritorio no se cerraría la ventana): se usa
           exitApp(), que confirma, intenta cerrar y luego va a logout.php. -->
      <a href="logout.php" class="quick-item quick-exit" onclick="exitApp(); return false;">
        <span class="nav-ico">🚪</span> Salir de la aplicación
      </a>
    </div>

    <div class="notif-box">
      <div class="notif-head">
        <span>🔔 Notificaciones</span>
        <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
      </div>
      <div class="notif-list" id="notif-list">
        <?php if ($notifs): ?>
          <?php foreach ($notifs as $n): ?>
            <a class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" href="<?= e($n['link'] ?? 'feed.php') ?>">
              <strong><?= e($n['actor'] ?? 'Airsoft Social') ?></strong>
              <span><?= e($n['message']) ?></span>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="empty">Sin notificaciones</span>
        <?php endif; ?>
      </div>
      <button class="btn-micro" onclick="markAllRead()">Marcar todo leído</button>
    </div>

    <div class="sidebar-foot">
      <button class="pwa-install" onclick="pwaInstall()">⬇️ Instalar la app (icono en escritorio / móvil)</button>
    </div>
  </aside>

  <!-- ===================== ÁREA DE TRABAJO ===================== -->
  <main class="content">
  <?php if (!empty($schemaIssues)): ?>
    <div class="alert error">
      <strong>La base de datos necesita una actualización.</strong>
      <?= e(reset($schemaIssues)) ?>
    </div>
  <?php endif; ?>
  <?= ad_banner_html() ?>
  <?php if ($updateBanner): ?>
    <div class="update-banner">
      <span>⬆️ Nueva versión <strong>v<?= e($updateBanner['latest']) ?></strong> de <?= e(APP_NAME) ?> disponible</span>
      <a class="btn btn-small btn-primary" href="updates.php">Ver y actualizar</a>
    </div>
  <?php endif; ?>
  <?php if (!empty($page_title)): ?>
    <div class="page-head">
      <h2><?= e($page_title) ?></h2>
      <?php if (!empty($page_subtitle)): ?><p><?= e($page_subtitle) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>