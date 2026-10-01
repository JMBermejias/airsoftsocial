<?php
/**
 * CONFIGURACIÓN DE LA BASE DE DATOS
 * ------------------------------------------------------------
 * Si usas el instalador (install.php) este archivo se generará
 * automáticamente. También puedes editarlo a mano.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'airsoft_social');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Prefijo de tablas (opcional) */
define('DB_PREFIX', '');

/* Título del sitio (se muestra en el navegador) */
define('APP_NAME', 'Airsoft Social');

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
define('GITHUB_REPO', 'JMBermejias/airsoftsocial');
define('UPDATE_CHECK_HOURS', 6);

/* Opcional: token de GitHub (solo lectura). Hace falta en dos casos:
 *   1) Si el repositorio de las actualizaciones es PRIVADO. Sin token, GitHub
 *      responde 404 a cualquier otra instalación y la app no puede ver las
 *      versiones nuevas ni descargar los paquetes.
 *   2) Para no sufrir el límite de la API (60 peticiones/h por IP) en
 *      hostings compartidos.
 * Se puede dejar vacío si el repositorio es público. NUNCA pongas aquí el
 * token de tu cuenta personal si estás en un equipo: usa uno "fine-grained"
 * con permiso solo de lectura de contenido de este repo. */
define('GITHUB_TOKEN', '');

/* Cada cuántas horas se comprueba (solo para administradores) si la base de
 * datos está al día y, si falta alguna tabla, se crea sola. Por defecto 6 h:
 * no hace falta tocarlo salvo que tu hosting sea muy lento. */
define('SCHEMA_CHECK_HOURS', 6);

/* Contraseña para poder abrir error_log.php (el registro de errores) sin
 * tener que iniciar sesión con una cuenta de administrador. Útil cuando la web
 * no carga y no puedes entrar, que es justo cuando más hace falta verlo.
 * Luego se abre con:  error_log.php?k=ESTA_CLAVE
 * Ponla solo mientras estés diagnosticando y bórrala después: quien la sepa
 * puede ver las rutas del servidor y mensajes técnicos. Si no la defines, la
 * página solo se abre entrando como administrador. */
define('DIAG_PASSWORD', '');

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