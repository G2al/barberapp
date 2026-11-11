// Service Worker con cache aggiornata per invalidare versioni precedenti
const CACHE_NAME = 'salvatore-napp-v15';
const ASSETS = [
  '/',
  '/index.html',
  '/dashboard.html',
  '/my-bookings.html',
  '/products.html',
  '/register.html',
  '/forgot-password.html',
  '/reset-password.html',
  '/css/style.css',
  '/js/auth.js',
  '/js/config.js',
  '/js/dashboard.js',
  '/js/products.js',
  '/js/script.js',
  // Immagini pesanti (sfondo, icone 512) escluse: finiscono in cache al primo uso
  // tramite il fetch handler, senza rallentare l'installazione su rete mobile.
  '/images/logo_barberia.png',
  '/images/logo-192x192.png',
  '/images/favicon-32x32.png',
  '/images/favicon-16x16.png',
  '/images/chrome.svg',
  '/images/edge.svg',
  '/images/safari.svg',
  '/favicon.ico',
  '/vender/bootstrap/css/bootstrap.min.css',
  '/vender/bootstrap/js/bootstrap.bundle.min.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) =>
        cache.addAll(
          ASSETS.map((asset) => new Request(asset, { cache: 'reload' }))
        )
      )
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      );

      await self.clients.claim();

      // NON forzare client.navigate(): ricaricava la pagina mentre il cliente
      // scriveva email/password (campo svuotato, tastiera chiusa).
      // Avvisa le pagine: decidono loro quando ricaricare in modo sicuro.
      const windows = await self.clients.matchAll({ type: 'window' });
      windows.forEach((client) => client.postMessage({ type: 'SW_UPDATED' }));
    })()
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET' || url.origin !== location.origin) {
    return;
  }

  // Preferisci network, fallback a cache
  event.respondWith(
    fetch(req, { cache: 'no-store' })
      .then((response) => {
        const respClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(req, respClone));
        return response;
      })
      .catch(() => caches.match(req))
  );
});
