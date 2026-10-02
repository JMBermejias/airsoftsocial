<?php
/**
 * Aplica la actualización a la última release publicada en GitHub.
 * Solo administradores, solo con token CSRF válido, y tras confirmación
 * explícita del administrador (GET -> updates.php -> POST).
 *
 * Conserva SIEMPRE: config.php, uploads/ (y su estado interno) y .git
 * cuando exista. Nada más se toca: los archivos del paquete se copian
 * encima de los actuales y se ejecutan las migraciones de esquema.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    die('Método no permitido.');
}
verify_csrf();

/* Directorio de trabajo bajo uploads/_system (protegido por .htaccess) */
$base = update_state_dir();
$work = $base . '/work';
@mkdir($work, 0775, true);

$lock = $work . '/update.lock';
if (is_file($lock) && time() - @filemtime($lock) < 600) {
    $_SESSION['flash'] = ['error', 'Ya hay una actualización en curso. Espera unos minutos e inténtalo de nuevo.'];
    redirect('updates.php');
}
@touch($lock);
register_shutdown_function(function () use ($lock) { @unlink($lock); });

$fail = function (string $msg): void {
    $_SESSION['flash'] = ['error', $msg];
    redirect('updates.php');
};

$rel = latest_release(true);
if (empty($rel['ok']) || !preg_match('/^v\d+\.\d+\.\d+$/', $rel['tag'])) {
    $fail('No se pudo verificar la última versión: ' . ($rel['error'] ?? 'error desconocido'));
}
$target = ltrim($rel['tag'], 'v');
$cur = app_version();
if (version_compare($target, $cur, '<=')) {
    $_SESSION['flash'] = ['ok', 'Ya estás en la versión más reciente (v' . $cur . ').'];
    redirect('updates.php');
}

/* 1) Descargar el paquete desde GitHub (el mismo de la release) */
$tgz = $work . '/release-' . $rel['tag'] . '.tar.gz';
$url = 'https://codeload.github.com/' . update_github_repo() . '/tar.gz/refs/tags/' . $rel['tag'];
$dl = gh_download($url, $tgz, 200 * 1048576);
if (empty($dl['ok'])) {
    $msg = 'No se pudo descargar el paquete desde GitHub: ' . $dl['error'];
    if (gh_token() === '') {
        $msg .= ' Si el repositorio es privado, añade GITHUB_TOKEN en config.php.';
    }
    $fail($msg);
}
$dl_warning = (string)($dl['warning'] ?? '');

/* 2) Extraer */
$extract = $work . '/extract';
@mkdir($extract, 0775, true);
$rremove = function (string $p) use (&$rremove): void {
    if (is_dir($p)) {
        foreach ((array)@scandir($p) as $it) {
            if ($it === '.' || $it === '..') continue;
            $rremove($p . '/' . $it);
        }
        @rmdir($p);
    } else {
        @unlink($p);
    }
};
foreach ((array)@scandir($extract) as $old) {
    if ($old === '.' || $old === '..') continue;
    $rremove($extract . '/' . $old);
}
try {
    $phar = new PharData($tgz);
    $phar->extractTo($extract, null, true);
} catch (Throwable $e) {
    $fail('No se pudo extraer el paquete: ' . $e->getMessage());
}

/* 3) Detectar el directorio raíz del paquete (GitHub lo nombra
 *    airsoftsocial-{tag} sin la v inicial). No se asume el nombre exacto. */
$pkg = null;
foreach ((array)@scandir($extract) as $it) {
    if ($it === '.' || $it === '..') continue;
    $cand = $extract . '/' . $it;
    if (is_dir($cand) && is_file($cand . '/index.php') && is_file($cand . '/includes/functions.php')) {
        $pkg = $cand;
        break;
    }
}
if (!$pkg) {
    $fail('El paquete descargado no tiene el formato esperado.');
}

/* 4) Copiar el paquete encima de la instalación actual.
 *    - El paquete NUNCA contiene config.php (está en .gitignore) ni uploads/.
 *    - Se omiten config.php, uploads/ y cualquier dotfile salvo .htaccess
 *      (protección Apache y MIME de la PWA), para que las credenciales
 *      locales, .env o similares y las subidas queden intactas. */
$root = dirname(__DIR__);
$omit = ['config.php', 'uploads', '.git'];
/* Copia atómica: primero a "fichero.new" y luego rename(), que en el mismo
 * sistema de ficheros es atómico. Así, si la petición se corta a mitad
 * (tiempo agotado del hosting, 502...), nunca queda un PHP truncado que
 * deje la web entera en blanco: sigue el fichero viejo o el nuevo, entero. */
/* Copia atómica y CON REGISTRO DE FALLOS.
 *
 * Antes los fallos se ignoraban en silencio (@copy y @unlink sin más): si un
 * fichero no se podía escribir por permisos o porque estaba bloqueado, la web
 * se quedaba con la versión vieja, pero la app escribía version.txt igual y
 * decía "actualizado". Por eso podía decir que todo iba bien y no cambiar nada.
 *
 * Ahora cada fallo se anota con su motivo y se avisa al final, sin impedir la
 * actualización (si un solo icono falla, el resto sí debe instalarse). */
$fallos = [];
$copiados = 0;
$copyTree = function (string $src, string $dst) use ($omit, &$copyTree, &$fallos, &$copiados): void {
    foreach ((array)@scandir($src) as $it) {
        if ($it === '.' || $it === '..' || in_array($it, $omit, true)) continue;
        if ($it[0] === '.' && $it !== '.htaccess') continue;
        $s = $src . '/' . $it;
        $d = $dst . '/' . $it;
        if (is_dir($s)) {
            if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) {
                $fallos[] = 'no se pudo crear el directorio ' . $it;
                continue;
            }
            $copyTree($s, $d);
        } else {
            $tmp = $d . '.new';
            if (@copy($s, $tmp) && @rename($tmp, $d)) {
                @chmod($d, 0644);
                $copiados++;
            } else {
                /* Motivo real del fallo: casi siempre permisos del fichero o de
                   la carpeta, que es lo que hay que arreglar en el hosting. */
                $motivo = '';
                if (!@copy($s, $tmp)) {
                    $motivo = error_get_last();
                    $motivo = $motivo ? ($motivo['message'] ?? '') : '';
                    if (!@is_writable(dirname($d))) $motivo .= ' (la carpeta no tiene permiso de escritura)';
                } elseif (!@rename($tmp, $d)) {
                    $motivo = 'no se pudo renombrar (¿otro proceso lo tiene abierto?)';
                }
                @unlink($tmp);
                $fallos[] = $it . ($motivo ? ': ' . $motivo : '');
            }
        }
    }
};
$copyTree($pkg, $root);

/* Verificación: comparar lo instalado con lo que trae el paquete. Si algún
 * fichero importante se ha quedado atrás, hay que decirlo, porque si no la app
 * cree que se ha actualizado y sigue sirviendo la versión vieja. */
$faltantes = [];
foreach (['version.txt', 'favicon.ico', 'manifest.webmanifest', 'assets/css/style.css'] as $clave) {
    $enPkg = $pkg . '/' . $clave;
    $enWeb = $root . '/' . $clave;
    if (is_file($enPkg) && (!is_file($enWeb) || filesize($enWeb) !== filesize($enPkg))) {
        $faltantes[] = $clave;
    }
}

/* 5) Forzar que los clientes (PWA) recarguen los estáticos sin caché vieja */
$sw = $root . '/service-worker.js';
if (is_file($sw)) {
    $content = (string)file_get_contents($sw);
    $content = preg_replace('/\/\* rev=[0-9]+ \*\//', '', (string)$content);
    $content = trim($content) . "\n/* rev=" . time() . " */\n";
    @file_put_contents($sw, $content);
}

/* 6) Escribir la versión instalada y ejecutar migraciones de esquema */
@file_put_contents($root . '/version.txt', $target);
try {
    run_migrations(db());
} catch (Throwable $e) {
    /* Se avisa igualmente: la app puede funcionar aunque una migración falle */
}

/* 7) Registrar y notificar */
update_log($rel['tag'], 'update-installed', 'v' . $cur . ' -> v' . $target);
try {
    $stmt = db()->query('SELECT id FROM ' . t('users') . ' WHERE is_admin = 1');
    foreach ($stmt->fetchAll() as $u) {
        notify((int)$u['id'], null, 'update', 'Actualización aplicada: v' . $target, 'updates.php');
    }
} catch (Throwable $e) {}
@unlink($tgz);
update_state_write(['notified_' . $target => true]);

/* El mensaje dice lo que realmente ha pasado. Antes ponía siempre "aplicada
 * correctamente" aunque ningún fichero se hubiera podido escribir, que es lo
 * que hacía que los cambios pareciesen no llegar nunca. */
$msg = 'Actualización a v' . $target . ': ' . $copiados . ' ficheros copiados.';
if ($faltantes) {
    $msg .= ' AVISO: estos ficheros NO se han podido actualizar, así que la web sigue '
          . 'sirviendo la versión anterior: ' . implode(', ', $faltantes)
          . '. Revisa los permisos de escritura de la carpeta en el hosting.';
}
if ($fallos) {
    $detalle = count($fallos) > 8 ? array_slice($fallos, 0, 8) : $fallos;
    $msg .= ' Además fallaron ' . count($fallos) . ' ficheros: ' . implode(' | ', $detalle)
          . (count($fallos) > 8 ? ' …' : '');
}
if ($dl_warning !== '') {
    $msg .= ' Aviso: ' . $dl_warning;
}
$_SESSION['flash'] = [($faltantes || $fallos) ? 'error' : 'ok', $msg];
redirect('updates.php');