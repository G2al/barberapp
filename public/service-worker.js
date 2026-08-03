// Service Worker con cache aggiornata per invalidare versioni precedenti
const CACHE_NAME = 'stile-infinito-v2';
const ASSETS = [
  '/',
  '/manifest.json',
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
  '/js/push-notifications.js',
  '/js/auth.js',
  '/js/config.js',
  '/js/dashboard.js',
  '/js/my-bookings.js',
  '/js/products.js',
  '/js/profile.js',
  '/js/spa.js',
  '/js/script.js',
  '/service-worker.js',
  '/images/apple-touch-icon.png',
  '/images/stile-infinito-logo.png',
  '/images/stile-infinito-logo-white.png',
  '/images/stile-infinito-salon.jpg',
  '/images/logo-192x192.png',
  '/images/logo-512x512.png',
  '/images/maskable-icon-192x192.png',
  '/images/maskable-icon-512x512.png',
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

self.addEventListener('push', (event) => {
  let payload = {};

  try {
    payload = event.data ? event.data.json() : {};
  } catch {
    payload = {
      title: 'Nuovo aggiornamento',
      body: event.data ? event.data.text() : '',
    };
  }

  const data = payload.data || {};
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/images/logo-192x192.png',
    badge: payload.badge || '/images/maskable-icon-192x192.png',
    tag: payload.tag || 'stile-infinito-notification',
    vibrate: payload.vibrate || [150, 75, 150],
    data: {
      ...data,
      url: data.url || '/my-bookings.html',
    },
  };

  event.waitUntil(
    self.registration.showNotification(payload.title || 'Stile Infinito', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = new URL(
    event.notification.data?.url || '/my-bookings.html',
    self.location.origin
  ).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clients) => {
        const sameOriginClient = clients.find((client) => {
          try {
            return new URL(client.url).origin === self.location.origin;
          } catch {
            return false;
          }
        });

        if (sameOriginClient) {
          return sameOriginClient.navigate(targetUrl).then(() => sameOriginClient.focus());
        }

        return self.clients.openWindow(targetUrl);
      })
  );
});
