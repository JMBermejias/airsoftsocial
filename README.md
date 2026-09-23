# 🎯 Social Airsoft

Red social para la comunidad de **airsoft** pensada para alojarse en cualquier **hosting compartido** (cPanel, Plesk, etc.) con **PHP 7.4+ / 8.x** y **MySQL / MariaDB**. Sin frameworks ni dependencias: solo PHP, MySQL y un poco de JavaScript.

---

## ✨ Funcionalidades

| Módulo | Descripción |
|---|---|
| **Registro / Login** | Creación de usuarios y sesión segura (contraseñas con `password_hash`). El **primer usuario** registrado puede marcarse **Administrador** (únicamente si aún no existe ningún admin). |
| **Panel izquierdo (dashboard)** | Navegación de la red: noticias, historias, amigos, campos, tienda y perfil. Incluye campanita de notificaciones y botón **Instalar la app** (icono de escritorio / móvil). |
| **Área de trabajo (centro)** | Muro de noticias con publicaciones de texto, **imágenes** y **PDF**, etiquetas `#tags`, likes y comentarios. |
| **Tienda online (panel derecho, siempre visible)** | Tienda **de afiliación** gestionada solo por el administrador. Cada producto tiene **ficha propia** (foto, precio, descripción) con las **formas de pago** que configure el admin (PayPal, Bizum, tarjeta, transferencia, efectivo) y su **enlace de afiliado**. |
| **Noticias generales** | Si el **administrador** publica con la casilla **⭐ Noticia general**, la publicación llega a **todos los usuarios** de la red, no solo a amigos. El resto de publicaciones solo las ven el autor y sus amigos. |
| **Amigos** | Enviar/aceptar solicitudes, ver solicitudes pendientes, buscar y eliminar amigos. Las noticias de amigos se muestran en el muro. |
| **Historias** | Historias **permanentes** por defecto (no se pierden). Si marcas la casilla «⏳ 24 horas» se borran solas al expirar. Con texto, **imagen o vídeo** (mp4/webm/mov), **solo las ven los amigos añadidos** (barra superior del muro y página de historias). |
| **Rangos militares** | Cada usuario sube de **rango según su antigüedad** (Soldado → … → Teniente General). El **Administrador siempre aparece como General**. |
| **Campos de juego** | Cualquier usuario puede **dar de alta un campo**: ubicación, tipo de juego, capacidad, precio, contacto, descripción y **requisitos de juego**. Los campos nuevos quedan **pendientes de aprobación** del administrador. |
| **Perfil completo** | Editar todos los datos (nombre, biografía, ubicación, experiencia, estilo, arma), **imagen de avatar** y contraseña. |
| **Subida de archivos** | Imágenes (JPG/PNG/GIF/WEBP), **PDF** y **vídeos** en publicaciones/avatares/campos y **vídeos** en historias. Validación de tipo MIME real. **Sin tope fijo de tamaño** en la app: los archivos se aceptan hasta el límite que permita tu hosting (`upload_max_filesize` / `post_max_size`). |
| **Actualización automática** | Cada 6 h la app comprueba si hay una **release más reciente** en GitHub y te **avisa** (campanilla 🔔, menú y banner). Con el botón **«Actualizar ahora»** se descarga e instala sola: `config.php`, `uploads/` y `.htaccess` quedan intactos junto con tus datos. |
| **Panel de administración** | Aprobar campos, gestionar productos y **formas de pago**, **editar datos y rol de los usuarios** (incluido restablecer contraseña), subir/bajar de rango administrativo y eliminar usuarios. |
| **App instalable (PWA)** | La red se instala como **aplicación con icono propio** en el escritorio (Chrome/Edge) y en el móvil (Android e iOS). |

---

## 🗂️ Estructura

```
/              → páginas (feed, perfil, amigos, campos, tienda, admin…)
/includes/     → funciones.php, header.php (panel izquierdo), footer.php (tienda derecha)
/actions/      → procesamiento de formularios (publicar, like, comentar, historias…)
/assets/css    → estilos
/assets/js     → interacciones (visor de historias, búsquedas…)
/assets/img    → avatar por defecto
/uploads/      → avatares, posts, stories, fields, products (subidas de los usuarios)
config.php     → credenciales de la base de datos
schema.sql     → esquema de base de datos
install.php    → instalador guiado (¡borrar después!)
```

---

## 🚀 Instalación en tu hosting

### Opción A — Con el instalador web (recomendada)

1. **Crea una base de datos** en tu hosting (cPanel → *MySQL Databases*). Anota: nombre, usuario y contraseña.
2. **Sube todos los archivos** de este proyecto a la carpeta `public_html` (o la que use tu hosting) mediante FTP o el administrador de archivos.
3. Da permisos de **escritura** a la carpeta `uploads/`:
   - cPanel → *File Manager* → clic derecho sobre `uploads` → *Change Permissions* → `755` (o `775`).
4. Abre en el navegador: `https://tu-dominio.com/install.php`
5. Rellena:
   - Datos de la base de datos (servidor, nombre, usuario, contraseña).
   - La cuenta **administradora** que quieras (ese usuario podrá publicar noticias generales y gestionar la tienda).
6. Pulsa **Instalar**. Al terminar te llevará al login.
7. 🔒 **ELIMINA `install.php` de tu servidor** (por seguridad).

### Opción B — Manual

1. Crea la base de datos y **importa `schema.sql`** desde phpMyAdmin.
2. Edita `config.php` con los datos de tu base de datos.
3. Crea tu cuenta registrándote en `index.php` y luego en phpMyAdmin pon `is_admin = 1` a tu usuario (en la tabla `users`). O ejecuta en SQL:
   ```sql
   UPDATE users SET is_admin = 1 WHERE username = 'TUNOMBRE';
   ```

---

## ⬆️ Actualización automática

Social Airsoft se mantiene al día sin tocar nada:

1. Cada **6 horas** (cuando entra un administrador) consulta GitHub (`GITHUB_REPO`, por defecto las releases oficiales de este repo).
2. Si hay una versión nueva, te llega un **aviso** en la campanilla, un distintivo en el menú **⬆️ Actualizar app** y un banner con el botón **Ver y actualizar**.
3. Desde **`updates.php`** ves la versión instalada, la última disponible, los cambios y pulsas **«Actualizar ahora»** (con tu confirmación).
4. La app se descarga sola desde GitHub y aplica la actualización **preservando `config.php`, `uploads/` y tus datos**. Las migraciones de base de datos se aplican solas y los navegadores recargan sin caché antigua.

Opciones de configuración (en `config.php`):

```php
define('GITHUB_REPO', 'JMBermejias/socialairsoft'); // repo de las actualizaciones
define('UPDATE_CHECK_HOURS', 6);                    // cada cuántas horas comprobar
define('GITHUB_TOKEN', '');                         // opcional: evita límites de la API
```

## 📱 App instalable (PWA) — escritorio y móvil

La web es una **Progressive Web App**: se instala **como una aplicación con su icono**, sin pasar por tiendas, y abre a pantalla completa.

- **Escritorio (Chrome/Edge):** el icono de instalación aparece en la barra de direcciones.
- **Móvil:** Chrome/Android → icono de menú → *Añadir a pantalla de inicio* / *Instalar app*; iPhone → Compartir → *Añadir a pantalla de inicio*.
- **Requisito:** acceso por **HTTPS** (tu hosting ya debe tenerlo si usas tu dominio).
- La instalación carga solo los estáticos desde caché y usa *network-first* para las páginas (nada de contenido obsoleto entre usuarios).
- También hay **`.apk` / `.aab`** (aplicación Android con WebView) en las releases, que abren directamente la web con su icono.

---

## 📦 Paquetes de release (.deb / .apk / .aab)

El repositorio incluye automatización para generar instaladores listos para descargar:

- **`.deb`** (Linux Debian/Ubuntu): instala la red social como `/var/www/socialairsoft` con `nginx` o `apache2` + `php-fpm` + `mariadb`.
- **`.apk` / `.aab`** (Android): app envoltorio con WebView que abre tu red social.

Cada vez que crees una nueva versión (ver `release.sh`) se generan automáticamente en [GitHub Releases](https://github.com/JMBermejias/socialairsoft/releases) mediante un workflow de GitHub Actions.

```
./release.sh "v1.0.0" "Mensaje de la versión"
```

---

## 🛡️ Seguridad incluida

- Sentencias preparadas (PDO) contra inyección SQL.
- Contraseñas cifradas con `password_hash` (bcrypt).
- Tokens **CSRF** en todos los formularios.
- Escape de salida con `htmlspecialchars`.
- Validación de subidas (tipo MIME real + tamaño) y carpeta `uploads/` sin ejecución de código.
- `config.php`, `includes/` y listados de directorios bloqueados vía `.htaccess`.

---

## ⚠️ Para desarrollo en local

Solo necesitas PHP y MySQL/MariaDB corriendo, y un `config.php` apuntando a ellos:

```bash
# Ejecución rápida del servidor de desarrollo
php -S localhost:8080   # con MariaDB/MySQL activo y config.php configurado
```

Después: `npm` no; **no hay dependencias que instalar** (PHP + MySQL únicamente). Las pruebas integrales del proyecto viven en `/tmp/opencode/integration_test.php`.

Hecho para la comunidad airsoft. 🎯