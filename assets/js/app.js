/* Social Airsoft · Interacciones del cliente */

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

/* ---- Instalación de la app (PWA): icono de escritorio / móvil ---- */
var deferredPrompt = null;
window.addEventListener('beforeinstallprompt', function(e){
  e.preventDefault();
  deferredPrompt = e;
});
function pwaInstall(){
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(choice){
      if (choice.outcome === 'accepted') deferredPrompt = null;
    });
    return;
  }
  var ua = navigator.userAgent;
  /* Firefox no implementa beforeinstallprompt: no admite instalación de PWA en escritorio */
  if (ua.indexOf('Firefox') !== -1) {
    if (ua.indexOf('Android') !== -1) {
      alert('Firefox móvil: pulsa el menú ⋮ → «Añadir a pantalla de inicio» para crear el icono de Social Airsoft.');
    } else {
      alert('Firefox (escritorio) no permite instalar aplicaciones web.\n\nOpciones:\n1) Abre esta web con Chrome o Edge y pulsa este botón: verás «Instalar Social Airsoft».\n2) Arrastra el icono de la pestaña (o la URL) al escritorio para crear un acceso directo.');
    }
    return;
  }
  if (/iPhone|iPad|iPod/.test(ua)) {
    alert('iOS: toca Compartir ⬆️ → «Añadir a pantalla de inicio».');
    return;
  }
  alert('Usa el menú del navegador → «Añadir a pantalla de inicio» o «Instalar Social Airsoft».');
}