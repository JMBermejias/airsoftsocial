/* Airsoft Social · Service Worker (PWA)
 * ----------
 * - Los archivos estáticos (css/js/imágenes/vídeos) se sirven desde caché
 *   mientras se refrescan en segundo plano (stale-while-revalidate).
 * - Las páginas dinámicas (feed, perfil…) usan red primero (network-first)
 *   para no servir contenido obsoleto a otro usuario.
 * - Solo actúa sobre el mismo origen; las subidas y las peticiones POST
 *   pasan directamente a la red.
 * Bump CACHE al hacer cambios en plantillas o assets para invalidar.
 */
const CACHE = 'airsoftsocial-v29';
const ASSET_RE = /\.(png|jpe?g|gif|webp|svg|ico|woff2?|pdf)$/;
/* El shell solo lleva lo que hace falta para arrancar sin conexión. El CSS y el
 * JS NO van aquí a propósito: se piden siempre por la red (ver el fetch), y
 * cachearlos con un ?v= distinto del que se pide solo llena la caché de
 * versiones inútiles. */
const SHELL = [
  './',
  './favicon.ico',
  './assets/img/default-avatar.svg',
  './assets/img/icons/icon-180.png',
  './assets/img/icons/icon-192.png',
  './assets/img/icons/icon-512.png',
  './assets/img/icons/icon-maskable.png'
];

/* El manifest y los iconos NO se cachean nunca. Van siempre a la red, porque
   el instalador de Android los lee al instalar y, si los sirviera de caché,
   seguiría poniendo el icono viejo para siempre. Además el manifest se pide
   por manifest.php y los iconos por icon.php, así que no entran ni por ASSET_RE. */
const ALWAYS_NETWORK_RE = /(\/manifest\.php|\/icon\.php|\/manifest\.webmanifest|\/favicon\.ico)$/;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll(SHELL))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // El manifest y los iconos van siempre a la red (ver ALWAYS_NETWORK_RE).
  // Se comprueba el pathname sin query, porque icon.php llega con ?src=...
  if (ALWAYS_NETWORK_RE.test(url.pathname)) {
    event.respondWith(networkFirst(req));
    return;
  }

  /* El CSS y el JS SIEMPRE a la red. Antes iban por stale-while-revalidate,
     que devuelve primero la copia cacheada: con el ?v= en la URL, cada versión
     nueva crea una entrada aparte y el móvil se quedaba con el fichero viejo
     (menú que no abría, botones mal) hasta reinstalar. Y si no hay red, se usa
     la copia cacheada, así que sin conexión sigue funcionando. */
  if (/\.(css|js)$/.test(url.pathname)) {
    event.respondWith(networkFirst(req));
    return;
  }

  // El resto de estáticos (imágenes, fuentes): caché primero, refresco después
  if (ASSET_RE.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(req));
    return;
  }

  // Navegaciones y páginas PHP: red primero, caché si no hay conexión
  if (req.mode === 'navigate') {
    event.respondWith(networkFirst(req));
  }
});

async function staleWhileRevalidate(req) {
  const cached = await caches.match(req);
  const network = fetch(req).then((res) => {
    if (res && res.ok) {
      const copy = res.clone();
      caches.open(CACHE).then((cache) => cache.put(req, copy));
    }
    return res;
  }).catch(() => cached);
  return cached || network;
}

async function networkFirst(req) {
  try {
    const res = await fetch(req);
    if (res && res.ok) {
      const copy = res.clone();
      caches.open(CACHE).then((cache) => cache.put(req, copy));
    }
    return res;
  } catch (err) {
    const cached = await caches.match(req);
    if (cached) return cached;
    const fallback = await caches.match('./');
    if (fallback) return fallback;
    return new Response('Sin conexión. Vuelve a conectarte para acceder a Airsoft Social.', {
      status: 503, statusText: 'Offline', headers: { 'Content-Type': 'text/plain; charset=utf-8' }
    });
  }
}
/* rev=1790173000 */
