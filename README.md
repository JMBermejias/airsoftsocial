# 🎯 Airsoft Social

Red social para la comunidad de **airsoft** pensada para alojarse en cualquier **hosting compartido** (cPanel, Plesk, etc.) con **PHP 7.4+ / 8.x** y **MySQL / MariaDB**. Sin frameworks ni dependencias: solo PHP, MySQL y un poco de JavaScript.

---

## ✨ Funcionalidades

| Módulo | Descripción |
|---|---|
| **Registro / Login** | Creación de usuarios y sesión segura (contraseñas con `password_hash`). Se entra **con el nombre de usuario o con el correo electrónico**, indistintamente (no hace falta acordarse de cuál se usó). El **primer usuario** registrado puede marcarse **Administrador** (únicamente si aún no existe ningún admin). |
| **Panel izquierdo (dashboard)** | Navegación de la red: noticias, historias, amigos, campos, tienda y perfil. Incluye campanita de notificaciones, botón **Salir de la aplicación** (🚪, cierra la sesión y la ventana si está instalada) y botón **Instalar la app** (icono en escritorio / móvil). |
| **Banner de publicidad** | Banner de **728 × 90 px** (el tamaño estándar) fijo en la **parte alta del área de trabajo (centro)**, que mide lo mismo: 728 px de ancho. La imagen **se adapta sola a su formato**: un banner apaisado (7.5:1 o más) ocupa los 728 × 90 enteros con el texto encima; un logo, una foto de producto o cualquier otra imagen se muestra **entera y sin deformar** a su lado. El administrador solo pega la **URL** del anuncio y la app **reconoce sola de dónde viene**: origen (YouTube, Amazon, Instagram, tu tienda…), título, descripción e imagen. Se activa, ordena o elimina desde **Gestionar anuncios**, con **vista previa en vivo** a 728 × 90. |
| **Área de trabajo (centro)** | Columna central de **728 px de ancho** (justo el ancho del banner, para que todo quede alineado). Muro de noticias con publicaciones de texto, **imágenes** y **PDF**, etiquetas `#tags`, likes y comentarios. |
| **Tienda online (panel derecho, siempre visible)** | Tienda **de afiliación** gestionada solo por el administrador. Cada producto tiene **ficha propia** (foto, precio, descripción) con las **formas de pago** que configure el admin (PayPal, Bizum, tarjeta, transferencia, efectivo) y su **enlace de afiliado**. |
| **Noticias generales** | Si el **administrador** publica con la casilla **⭐ Noticia general**, la publicación llega a **todos los usuarios** de la red, no solo a amigos. El resto de publicaciones solo las ven el autor y sus amigos. |
| **Amigos** | Enviar/aceptar solicitudes, ver solicitudes pendientes, buscar y eliminar amigos. Las noticias de amigos se muestran en el muro. |
| **Historias** | Historias **permanentes** por defecto (no se pierden). Si marcas la casilla «⏳ 24 horas» se borran solas al expirar. Con texto, **imagen o vídeo** (mp4/webm/mov), **solo las ven los amigos añadidos** (barra superior del muro y página de historias). |
| **Rangos militares** | Cada usuario sube de **rango según su antigüedad** (Soldado → … → Teniente General). El **Administrador siempre aparece como General**. |
| **Campos de juego** | Cualquier usuario puede **dar de alta un campo**: ubicación, tipo de juego, capacidad, precio, contacto, descripción y **requisitos de juego**. Los campos nuevos quedan **pendientes de aprobación** del administrador. |
| **Perfil completo** | Editar todos los datos (nombre, biografía, ubicación, experiencia, estilo, arma), **imagen de avatar** y contraseña. |
| **Subida de archivos** | Imágenes (JPG/PNG/GIF/WEBP), **PDF** y **vídeos** en publicaciones/avatares/campos y **vídeos** en historias. Validación de tipo MIME real. **Sin tope fijo de tamaño** en la app: los archivos se aceptan hasta el límite que permita tu hosting (`upload_max_filesize` / `post_max_size`). |
| **Actualización automática** | Cada 6 h la app comprueba si hay una **release más reciente** en GitHub y te **avisa** (campanilla 🔔, menú y banner). Con el botón **«Actualizar ahora»** se descarga e instala sola: `config.php`, `uploads/` y `.htaccess` quedan intactos junto con tus datos. |
| **Panel de administración** | Aprobar campos, gestionar productos y **formas de pago**, **gestionar los anuncios del banner**, **editar datos y rol de los usuarios** (incluido restablecer contraseña), subir/bajar de rango administrativo y eliminar usuarios. |
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
/uploads/      → avatares, posts, stories, fields, products, banners (subidas de los usuarios)
config.php     → credenciales de la base de datos
schema.sql     → esquema de base de datos
install.php    → instalador guiado (¡borrar después!)
health.php     → diagnóstico de la instalación (funciona sin base de datos)
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

Airsoft Social se mantiene al día sin tocar nada:

1. Cada **6 horas** (cuando entra un administrador) consulta GitHub (`GITHUB_REPO`, por defecto las releases oficiales de este repo).
2. Si hay una versión nueva, te llega un **aviso** en la campanilla, un distintivo en el menú **⬆️ Actualizar app** y un banner con el botón **Ver y actualizar**.
3. Desde **`updates.php`** ves la versión instalada, la última disponible, los cambios y pulsas **«Actualizar ahora»** (con tu confirmación).
4. La app se descarga sola desde GitHub y aplica la actualización **preservando `config.php`, `uploads/` y tus datos**. Las migraciones de base de datos se aplican solas y los navegadores recargan sin caché antigua.

Opciones de configuración (en `config.php`):

```php
define('GITHUB_REPO', 'JMBermejias/airsoftsocial'); // repo de las actualizaciones
define('UPDATE_CHECK_HOURS', 6);                    // cada cuántas horas comprobar
define('GITHUB_TOKEN', '');                         // opcional: solo si el repo es privado
```

**Si la comprobación falla**, la propia pantalla de actualizaciones te dice exactamente por qué
(`curl` desactivado, `allow_url_fopen` desactivado, repositorio privado sin token, límite de la API…)
con un diagnóstico completo, en lugar de un error genérico. La app necesita poder salir a Internet
por HTTPS: usa la extensión **cURL** y, si no la tiene, cae a los flujos de PHP.

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

- **`.deb`** (Linux Debian/Ubuntu): instala la red social como `/var/www/airsoftsocial` con `nginx` o `apache2` + `php-fpm` + `mariadb`.
- **`.apk` / `.aab`** (Android): app envoltorio con WebView que abre tu red social.

Cada vez que crees una nueva versión (ver `release.sh`) se generan automáticamente en [GitHub Releases](https://github.com/JMBermejias/airsoftsocial/releases) mediante un workflow de GitHub Actions.

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

## 🩺 Si algo no carga: `health.php`

Abre `https://tu-dominio.com/health.php`. Es una página de diagnóstico que **funciona
incluso sin base de datos ni sesión**, pensado justo para cuando la web no arranca
(pantalla en blanco, error 500 o **502**). Comprueba y te dice:

- versión de PHP, si hay **cURL**, `allow_url_fopen`, límites de ejecución y memoria;
- que los archivos esenciales estén y **sin errores de sintaxis** (una subida cortada);
- permisos de escritura de la app y de `uploads/`;
- conexión con la base de datos y **qué tablas faltan**;
- si el servidor puede salir a Internet hacia GitHub.

No muestra datos personales ni tus credenciales, y **puedes borrarla** cuando quieras:
la app no la necesita. Si detecta algo raro, te dice qué hacer y te da el `CREATE TABLE`
listo para pegar en phpMyAdmin.

Un **502** casi nunca es un fallo de la app: suele ser que el servidor se queda sin
memoria o sin tiempo. Si `health.php` está todo en verde y la web sigue sin cargar,
pide a tu hosting que aumente el límite de memoria y de tiempo de PHP.

---

## 🔑 Entrar con nombre de usuario o con correo

El login acepta **las dos cosas**: el nombre con el que te registraste
(`sniper_24`) o tu correo (`juan@correo.com`). Da igual cuál uses.

Si no funciona, la app te dice exactamente por qué, en vez de un error genérico:

- **«No hay ninguna cuenta con …»** → el nombre de usuario está mal escrito (va
  sin arroba, sin espacios, y solo con letras, números y `_`). El correo sí
  admite atajos: con `nombre@` ya vale.
- **«La contraseña no es correcta»** → el nombre existe, falla la contraseña. Si
  no la recuerdas, un administrador puede cambiarla desde el panel.

**Cuentas antiguas sin nombre de usuario.** Si tu base de datos venía de una
versión anterior, puede haber cuentas que se quedaron sin nombre y que solo
entran por correo. El panel de administración lo detecta, lo avisa arriba con un
aviso rojo y tiene un botón que les asigna un nombre automáticamente (sacado del
correo, sin repetir ninguno). Una vez reparado, esa cuenta entra con nombre y
con correo.

---

## 🖼️ La imagen del banner se adapta a su formato

El banner mide 728 × 90 px, o sea 8.09 : 1. No todas las imágenes tienen esa
forma, así que la app **mide la imagen** (una sola vez; luego lo guarda) y la
enseña como mejor queda. **Ninguna imagen se deforma y solo se recorta si es un
banner de verdad**:

| Imagen | Proporción | Cómo se muestra |
|---|---|---|
| Banner 728 × 90 | 8.09 : 1 | Ocupa los **728 × 90 enteros**, con el texto encima sobre un degradado |
| Banner 1500 × 200 | 7.5 : 1 | Igual, ocupa los 728 × 90 (recorta un 9 %, imperceptible) |
| Banner 1200 × 300 | 4 : 1 | **Entera** a su lado, a 330 × 82, sin recortar |
| Logo 400 × 400 | 1 : 1 | **Entero**, a 88 × 88, sin recortar |
| Foto vertical 600 × 900 | 0.67 : 1 | **Entera**, a 59 × 88, sin recortar |
| Sin imagen | — | El texto ocupa los 728 px |

La medida se guarda en `uploads/_system/state.json`, así que en cada visita no se
vuelve a mirar la imagen (coste: 0,00001 s). Se mide al guardar el anuncio, al
pulsar «Rellenar datos del enlace» y al abrir el panel de anuncios.

Para cambiar de un comportamiento a otro, mira el umbral `7.5` en
`banner_image_is_wide()` (`includes/functions.php`).

---

## ⚠️ Para desarrollo en local

Solo necesitas PHP y MySQL/MariaDB corriendo, y un `config.php` apuntando a ellos:

```bash
# Ejecución rápida del servidor de desarrollo
php -S localhost:8080   # con MariaDB/MySQL activo y config.php configurado
```

Después: `npm` no; **no hay dependencias que instalar** (PHP + MySQL únicamente). Las pruebas integrales del proyecto viven en `/tmp/opencode/integration_test.php`.

Hecho para la comunidad airsoft. 🎯