// Service Worker con cache aggiornata per invalidare versioni precedenti
const CACHE_NAME = 'alettabarber-v8';
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
  '/js/push-notifications.js',
  '/images/logo-192x192.png',
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

self.addEventListener('push', (event) => {
  let payload = {};

  if (event.data) {
    try {
      payload = event.data.json();
    } catch {
      payload = { body: event.data.text() };
    }
  }

  const title = payload.title || 'Aletta Barber';
  const options = {
    body: payload.body || 'Hai una nuova notifica.',
    icon: payload.icon || '/images/logo-192x192.png',
    badge: payload.badge || '/images/logo-192x192.png',
    image: payload.image,
    actions: payload.actions || [],
    data: payload.data || { url: '/dashboard.html' },
    tag: payload.tag,
    renotify: payload.renotify,
    requireInteraction: payload.requireInteraction,
    vibrate: payload.vibrate,
  };

  Object.keys(options).forEach((key) => {
    if (options[key] === undefined) {
      delete options[key];
    }
  });

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  let targetUrl = new URL('/dashboard.html', self.location.origin);

  try {
    const requestedUrl = new URL(
      event.notification.data?.url || '/dashboard.html',
      self.location.origin
    );

    if (requestedUrl.origin === self.location.origin) {
      targetUrl = requestedUrl;
    }
  } catch {
    // Mantiene la destinazione sicura predefinita.
  }

  event.waitUntil(
    self.clients.matchAll({
      type: 'window',
      includeUncontrolled: true,
    }).then(async (windows) => {
      const existingWindow = windows.find((client) =>
        new URL(client.url).origin === self.location.origin
      );

      if (existingWindow) {
        await existingWindow.navigate(targetUrl.href);
        return existingWindow.focus();
      }

      return self.clients.openWindow(targetUrl.href);
    })
  );
});
