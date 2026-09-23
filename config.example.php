<?php
/**
 * CONFIGURACIÓN DE LA BASE DE DATOS
 * ------------------------------------------------------------
 * Si usas el instalador (install.php) este archivo se generará
 * automáticamente. También puedes editarlo a mano.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'social_airsoft');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Prefijo de tablas (opcional) */
define('DB_PREFIX', '');

/* Título del sitio (se muestra en el navegador) */
define('APP_NAME', 'Social Airsoft');

/* Zona horaria por defecto */
date_default_timezone_set('Europe/Madrid');

/* ------------------------------------------------------------
 * ACTUALIZACIONES AUTOMÁTICAS  (opcional)
 * La app comprueba si hay una release más reciente en GitHub y te avisa
 * como administrador. Tú decides cuándo aplicarla (botón "Actualizar").
 *   GITHUB_REPO         → repo de donde salen las actualizaciones.
 *   UPDATE_CHECK_HOURS  → cada cuántas horas se comprueba (defecto 6).
 * Deja esto como está si usas las releases oficiales.
 * ------------------------------------------------------------ */
define('GITHUB_REPO', 'JMBermejias/socialairsoft');
define('UPDATE_CHECK_HOURS', 6);

/* Opcional: token de GitHub (solo lectura) para que la comprobación no se
 * vea afectada por el límite de la API (60 peticiones/h por IP) en hostings
 * compartidos. Se puede dejar vacío: funcionará igual en la mayoría de casos.
 * NUNCA pongas aquí el token de tu cuenta si estás en un equipo: usa uno
 * "fine-grained" con permiso solo de lectura de contenido de este repo. */
define('GITHUB_TOKEN', '');

/* ------------------------------------------------------------
 * RANGOS MILITARES (opcional)
 * Los usuarios suben de rango según los días desde su alta. El
 * Administrador siempre aparece como General. Si quieres ajustar la
 * tabla, descomenta y cambia este mapa (días de antigüedad => rango):
 * ------------------------------------------------------------ */
/*
define('MILITARY_RANKS', [
    0    => 'Soldado',
    30   => 'Soldado de 1ª',
    90   => 'Cabo',
    180  => 'Sargento',
    365  => 'Teniente',
    730  => 'Capitán',
    1095 => 'Comandante',
    1460 => 'Coronel',
    1825 => 'Teniente General',
]);
*/