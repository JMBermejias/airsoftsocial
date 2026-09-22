# 🎯 Social Airsoft

Red social para la comunidad de **airsoft** pensada para alojarse en cualquier **hosting compartido** (cPanel, Plesk, etc.) con **PHP 7.4+ / 8.x** y **MySQL / MariaDB**. Sin frameworks ni dependencias: solo PHP, MySQL y un poco de JavaScript.

---

## ✨ Funcionalidades

| Módulo | Descripción |
|---|---|
| **Registro / Login** | Creación de usuarios y sesión segura (contraseñas con `password_hash`). |
| **Panel izquierdo (dashboard)** | Navegación de la red: noticias, historias, amigos, campos, tienda y perfil. Incluye campanita de notificaciones. |
| **Área de trabajo (centro)** | Muro de noticias con publicaciones de texto, **imágenes** y **PDF**, etiquetas `#tags`, likes y comentarios. |
| **Tienda online (panel derecho, siempre visible)** | El sidebar derecho muestra tus productos (imagen, precio, botón de compra con enlace). Solo el administrador la gestiona. |
| **Noticias generales** | Si el **administrador** publica con la casilla **⭐ Noticia general**, la publicación llega a **todos los usuarios** de la red, no solo a amigos. El resto de publicaciones solo las ven el autor y sus amigos. |
| **Amigos** | Enviar/aceptar solicitudes, ver solicitudes pendientes, buscar y eliminar amigos. Las noticias de amigos se muestran en el muro. |
| **Historias** | Historias de **24 horas** con texto y/o imagen. **Solo las ven los amigos añadidos** (aparecen en la barra superior del muro y en la página de historias). |
| **Campos de juego** | Cualquier usuario puede **dar de alta un campo**: ubicación, tipo de juego, capacidad, precio, contacto, descripción y **requisitos de juego**. Los campos nuevos quedan **pendientes de aprobación** del administrador. |
| **Perfil completo** | Editar todos los datos (nombre, biografía, ubicación, experiencia, estilo, arma), **imagen de avatar** y contraseña. |
| **Subida de archivos** | Imágenes (JPG/PNG/GIF/WEBP) y **PDF** en publicaciones, avatares y campos. Validación de tipo y tamaño (máx. 10 MB). |
| **Panel de administración** | Aprobar campos, gestionar productos, subir/bajar de rol a usuarios y eliminar usuarios. |

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

```bash
# Opción fácil (sin dependencias): usar el binario de PHP de tu sistema
curl -sSf https://getcomposer.org/installer | php

# En este repo también hay un entorno de pruebas montado con Homebrew:
#   ~/brew/opt/php/sbin/php-fpm, ~/brew/bin/mariadbd
# Prueba rápida:
~/brew/bin/php -S localhost:8080   # (con MariaDB corriendo y config.php apuntando a localhost)
```

Ver `docs/` para más detalles.

Hecho para la comunidad airsoft. 🎯