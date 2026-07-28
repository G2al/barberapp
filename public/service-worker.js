// Service Worker con cache aggiornata per invalidare versioni precedenti
const CACHE_NAME = 'giovannicerino-v42';
const ASSETS = [
  '/',
  '/index.html',
  '/dashboard.html',
  '/my-bookings.html',
  '/products.html',
  '/profile.html',
  '/register.html',
  '/forgot-password.html',
  '/reset-password.html',
  '/css/style.css',
  '/css/app-ui.css',
  '/js/app-ui.js',
  '/js/auth.js',
  '/js/config.js',
  '/js/dashboard.js',
  '/js/products.js',
  '/js/profile.js',
  '/js/script.js',
  '/service-worker.js',
  '/vender/bootstrap/css/bootstrap.min.css',
  '/vender/bootstrap/js/bootstrap.bundle.min.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET' || url.origin !== location.origin) {
    return;
  }

  // Preferisci network, fallback a cache
  event.respondWith(
    fetch(req)
      .then((response) => {
        const respClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(req, respClone));
        return response;
      })
      .catch(() => caches.match(req))
  );
});
