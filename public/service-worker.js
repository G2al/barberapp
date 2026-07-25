// Service Worker con cache aggiornata per invalidare versioni precedenti
const CACHE_NAME = 'alettabarber-v6';
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

      // iOS può mantenere visibile la pagina sospesa anche dopo l'attivazione
      // del nuovo worker: forza una navigazione per mostrare subito la versione nuova.
      const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });
      await Promise.all(
        windows.map((client) =>
          client.navigate(client.url).catch(() => null)
        )
      );
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
