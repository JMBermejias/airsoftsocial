<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
verify_csrf();

/* Si el código llegó antes que la base de datos (subida a mano, actualización
 * fallida), se crea aquí lo que falte. Sin esto el guardado daba un error 500
 * en blanco del que no se puede saber la causa. */
/* Forzado: aquí el usuario está intentando guardar, así que se comprueba y
 * repara aunque el autoparchado normal ya lo intentara hace poco. */
$issues = ensure_schema(true);
if (!empty($issues)) {
    $_SESSION['flash'] = ['error', reset($issues)];
    redirect('banner_admin.php');
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $sort = (int)($_POST['sort_order'] ?? 0);

    /* El título NO es obligatorio: hay anuncios que son solo una imagen
     * (un logo o un banner 728x90) y el texto sobra. Si no hay título pero sí
     * imagen, el banner se dibuja con la imagen sola. */
    if (!preg_match('#^https?://#i', $url)) {
        $_SESSION['flash'] = ['error', 'El enlace del anuncio debe ser una URL válida que empiece por http:// o https:// (así nunca abrirá una página en blanco).'];
        redirect('banner_admin.php');
    }
    $desc = mb_substr($desc, 0, 400);
    $source = mb_substr($source, 0, 120);

    $image = null;
    $imgWarn = '';
    $oldImage = '';
    if ($id) {
        $stmt = db()->prepare('SELECT image FROM ' . t('ad_banners') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $oldImage = (string)($stmt->fetch()['image'] ?? '');
        $image = $oldImage !== '' ? $oldImage : null;
    }
    if (!empty($_FILES['image']['name'])) {
        $up = upload_file($_FILES['image'], 'banners', 'image');
        if (!$up['ok']) { $_SESSION['flash'] = ['error', $up['error']]; redirect('banner_admin.php'); }
        $image = $up['path'];
    } elseif (trim((string)($_POST['image_url'] ?? '')) !== '') {
        /* El administrador pega a mano la dirección de la imagen (por si la
         * web del anuncio bloquea la descarga automática). Se intenta bajar a
         * uploads/banners/ y, si tampoco eso deja, se enlaza la original. */
        $imgUrl = trim($_POST['image_url']);
        if (!preg_match('#^https?://#i', $imgUrl) || strlen($imgUrl) > REMOTE_URL_MAX) {
            $_SESSION['flash'] = ['error', 'La dirección de la imagen no es válida: tiene que empezar por http:// o https://.'];
            redirect('banner_admin.php');
        }
        $dl = save_remote_image($imgUrl, 'banners', $url);
        if ($dl['ok']) {
            $image = $dl['path'];
        } else {
            /* El anuncio se guarda igual, pero se dice por qué no se ha podido
             * bajar la imagen en vez de un genérico, para que el administrador
             * sepa si es un 403, un certificado caducado o un fichero enorme. */
            $image   = $imgUrl;
            $imgWarn = '⚠️ ' . $dl['error'] . ' La imagen se ha enlazado desde su web original; '
                     . 'si no se ve en el banner, súbela tú con «📷 Subir imagen». ';
        }
    } elseif (!empty($_POST['image_imported'])) {
        /* Imagen detectada automáticamente desde la URL del anuncio:
         * puede ser una subida local en uploads/ o la URL remota original. */
        $imp = trim($_POST['image_imported']);
        if (strpos($imp, '..') === false && is_file(dirname(__DIR__) . '/' . $imp)) {
            $image = $imp;
        } elseif (preg_match('#^https?://#i', $imp) && strlen($imp) <= REMOTE_URL_MAX) {
            $image = $imp;
        }
    }

    /* Si la imagen ha cambiado se borra el fichero anterior. Si no, cada
     * edición del anuncio dejaba su imagen vieja en uploads/banners/ para
     * siempre, y como hay tres vías distintas para poner una imagen (subir,
     * importar del enlace o pegar la URL) se acumulaban rápido. */
    $newImage = (string)$image;
    if ($oldImage !== '' && $oldImage !== $newImage
        && strpos($oldImage, 'uploads/') === 0 && strpos($oldImage, '..') === false) {
        $oldPath = dirname(__DIR__) . '/' . $oldImage;
        if (is_file($oldPath)) @unlink($oldPath);
    }

    try {
        if ($id) {
            db()->prepare('UPDATE ' . t('ad_banners') . ' SET title=?, description=?, url=?, source=?, image=?, active=?, sort_order=? WHERE id=?')
                ->execute([$title, $desc ?: null, $url, $source ?: null, $image, $active, $sort, $id]);
            $_SESSION['flash'] = ['ok', 'Anuncio actualizado.' . $imgWarn];
        } else {
            db()->prepare('INSERT INTO ' . t('ad_banners') . ' (title, description, url, source, image, active, sort_order) VALUES (?,?,?,?,?,?,?)')
                ->execute([$title, $desc ?: null, $url, $source ?: null, $image, $active, $sort]);
            $_SESSION['flash'] = ['ok', 'Anuncio añadido: ya aparece en la parte alta del área de trabajo.' . $imgWarn];
        }
    } catch (PDOException $ex) {
        /* Nunca una pantalla en blanco: se explica qué ha pasado. */
        $_SESSION['flash'] = ['error', 'No se pudo guardar el anuncio (' . $ex->getMessage() . ').'];
    }
    /* Se mide la imagen ahora (una sola vez) para saber si es apaisada y
     * poder enseñarla bien, en vez de recortarla siempre por el mismo lado. */
    if (!empty($image)) banner_image_size((string)$image);
    redirect('banner_admin.php');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        db()->prepare('DELETE FROM ' . t('ad_banners') . ' WHERE id = ?')->execute([$id]);
        $_SESSION['flash'] = ['ok', 'Anuncio eliminado.'];
    } catch (PDOException $ex) {
        $_SESSION['flash'] = ['error', 'No se pudo eliminar el anuncio (' . $ex->getMessage() . ').'];
    }
    redirect('banner_admin.php');
}

redirect('banner_admin.php');
