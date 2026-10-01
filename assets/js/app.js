/* Airsoft Social · Interacciones del cliente */

/* ---- Service worker ----
 * Cuando entra una versión nueva, el service worker nuevo espera a que se cierre
 * la pestaña. Con esto se recarga solo al cambiar, que es lo que hace que los
 * cambios se vean sin tener que cerrar y abrir el navegador a mano. */
if (navigator.serviceWorker) {
  var reloading = false;
  navigator.serviceWorker.addEventListener('controllerchange', function () {
    if (reloading) return;
    reloading = true;
    location.reload();
  });
}

/* ---- Menú del móvil ----
 * En móvil el panel lateral es una barra superior con un botón que abre el menú
 * desplegado (notificaciones, instalar la app, salir…). En escritorio el botón
 * no se ve, así que todo esto no hace nada y el menú está siempre abierto. */
(function () {
  var sidebar  = document.getElementById('app-sidebar');
  var toggle   = document.getElementById('menu-toggle');
  var backdrop = document.getElementById('menu-backdrop');
  if (!sidebar || !toggle) return;

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('show', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    /* Al abrir se bloquea el scroll del fondo, para que el dedo no mueva la
       página por detrás del menú. */
    document.body.style.overflow = open ? 'hidden' : '';
  }

  toggle.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('open'));
  });
  if (backdrop) {
    backdrop.addEventListener('click', function () { setOpen(false); });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
  /* Al pulsar un enlace del menú se cierra (por si hay navegación interna). */
  sidebar.addEventListener('click', function (e) {
    if (e.target.closest('a') && sidebar.classList.contains('open')) {
      setOpen(false);
    }
  });
  /* Si la ventana se ensancha (giro del móvil), el menú vuelve a estar siempre
     visible, así que se sueltan el estado y el scroll. */
  window.addEventListener('resize', function () {
    if (window.innerWidth > 880) setOpen(false);
  });
})();

/* ---- Visor de historias ---- */
function openStory(data){
  var v = document.getElementById('story-viewer');
  document.getElementById('sv-user').textContent = data.user;
  document.getElementById('sv-avatar').src = data.avatar || 'assets/img/default-avatar.svg';
  var txt = document.getElementById('sv-text');
  var img = document.getElementById('sv-img');
  var vid = document.getElementById('sv-video');
  txt.textContent = data.text || '';
  if (data.image) { img.src = data.image; img.style.display = ''; }
  else { img.style.display = 'none'; }
  if (data.video) {
    vid.src = data.video;
    vid.style.display = '';
    vid.load();
  } else {
    vid.src = '';
    vid.style.display = 'none';
  }
  v.classList.remove('hidden');
}
function closeStory(){
  document.getElementById('story-viewer').classList.add('hidden');
  var vid = document.getElementById('sv-video');
  if (vid) { vid.pause(); vid.currentTime = 0; }
}
document.addEventListener('keydown', function(ev){
  if (ev.key === 'Escape') closeStory();
});
document.getElementById('story-viewer') && document.getElementById('story-viewer').addEventListener('click', function(ev){
  if (ev.target === this) closeStory();
});

/* ---- Modal de crear historia ---- */
function openStoryCreate(){
  var m = document.getElementById('story-create');
  if (m) m.classList.remove('hidden');
}

/* ---- Marcar notificaciones leídas ---- */
function markAllRead(){
  if (!window.CS_RF) return;
  var body = new URLSearchParams();
  body.append('csrf', window.CS_RF);
  fetch('actions/notification.php', { method: 'post', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
    .then(function(){ document.querySelectorAll('.notif-item').forEach(function(i){ i.classList.remove('unread'); }); })
    .catch(function(){});
}
/* token csrf para peticiones JS (lo tomamos del primer formulario) */
var __csrfInput = document.querySelector('input[name=csrf]');
if (__csrfInput) window.CS_RF = __csrfInput.value;

/* ---- Búsqueda de usuarios en la página de amigos ---- */
function bindUserSearch(){
  var box = document.getElementById('user-search');
  if (!box) return;
  box.addEventListener('input', function(){
    var t = this.value.toLowerCase().trim();
    document.querySelectorAll('#all-users .user-card').forEach(function(card){
      var hay = (card.dataset.search || '').indexOf(t) !== -1;
      card.style.display = hay ? '' : 'none';
    });
  });
}
document.addEventListener('DOMContentLoaded', bindUserSearch);

/* ---- Previsualización de avatar ---- */
function previewAvatar(input){
  var img = document.getElementById('avatar-preview');
  if (!img || !input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e){ img.src = e.target.result; };
  reader.readAsDataURL(input.files[0]);
}

/* ---- Salir de la aplicación (cierra sesión y cierra la ventana si es la app instalada) ---- */
function confirmExit(){
  return window.confirm('¿Salir de la aplicación? Se cerrará tu sesión.');
}
function exitApp(){
  if (!confirmExit()) return;
  try { window.close(); } catch (e) {}
  window.location.href = 'logout.php';
}

/* ---- Instalación de la app (PWA): icono de escritorio / móvil ----
 *
 * Android y Chrome SOLO crean el icono si todo esto se cumple:
 *  1. La web en HTTPS. Sin HTTPS no hay service worker y no hay icono.
 *  2. El manifest debe cargar y traer iconos de 192 y 512.
 *  3. El service worker debe estar activo.
 * Si falta el punto 1, no hay nada que arreglar en el código: es el hosting. */
var deferredPrompt = null;

function isHttps() {
  return location.protocol === 'https:'
      || location.hostname === 'localhost'
      || location.hostname === '127.0.0.1';
}

window.addEventListener('beforeinstallprompt', function (e) {
  e.preventDefault();
  deferredPrompt = e;
  showInstallHint('');
});

window.addEventListener('appinstalled', function () {
  deferredPrompt = null;
  var hint = document.getElementById('install-hint');
  if (hint) hint.hidden = true;
});

/* Aviso visible en la barra superior. Se enseña solo el botón cuando el
   navegador confirma que se puede instalar, o el motivo cuando no se puede. */
function showInstallHint(why) {
  var hint = document.getElementById('install-hint');
  var whyEl = document.getElementById('install-hint-why');
  if (!hint) return;
  hint.hidden = false;
  if (whyEl) whyEl.textContent = why || '';
}

/* Al cargar, si el navegador no ha lanzado el evento de instalación, se
   comprueba por qué: casi siempre es que la web va por HTTP, y decirlo ahorra
   bastante tiempo. */
setTimeout(function () {
  if (deferredPrompt) return;                 /* ya se puede instalar */
  var hint = document.getElementById('install-hint');
  if (!hint) return;
  if (!isHttps()) {
    showInstallHint('Necesita HTTPS para poder crear el icono en el móvil.');
    return;
  }
  if (navigator.serviceWorker && navigator.serviceWorker.controller) return;
  /* HTTPS y sin service worker activo: aún no está listo, pero no es un problema. */
}, 2500);

function pwaInstall(){
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(choice){
      if (choice.outcome === 'accepted') deferredPrompt = null;
    });
    return;
  }
  var ua = navigator.userAgent;
  if (!isHttps()) {
    alert('Para poder crear el icono en el móvil, la web tiene que ir por HTTPS (con ladirección https:// y el candado).\n\nAhora mismo se está viendo por http://, y los móviles Android no instalan aplicaciones web sin HTTPS.\n\nEs cosa del alojamiento: hay que activar el certificado SSL gratuito. En la mayoría de hostings se hace desde el panel, en «SSL» o «Certificados».');
    return;
  }
  /* Firefox no implementa beforeinstallprompt: no admite instalación de PWA en escritorio */
  if (ua.indexOf('Firefox') !== -1) {
    if (ua.indexOf('Android') !== -1) {
      alert('Firefox móvil: pulsa el menú ⋮ → «Añadir a pantalla de inicio» para crear el icono de Airsoft Social.');
    } else {
      alert('Firefox (escritorio) no permite instalar aplicaciones web.\n\nOpciones:\n1) Abre esta web con Chrome o Edge y pulsa este botón: verás «Instalar Airsoft Social».\n2) Arrastra el icono de la pestaña (o la URL) al escritorio para crear un acceso directo.');
    }
    return;
  }
  if (/iPhone|iPad|iPod/.test(ua)) {
    alert('iOS: toca Compartir ⬆️ → «Añadir a pantalla de inicio».');
    return;
  }
  alert('Usa el menú del navegador → «Añadir a pantalla de inicio» o «Instalar Airsoft Social».');
}