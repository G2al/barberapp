const CACHE_NAME = 'giovannicerino-push-v2';
const PAGES = ['/', '/index.html', '/dashboard.html', '/my-bookings.html', '/products.html',
  '/register.html', '/forgot-password.html', '/reset-password.html'];
self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await Promise.allSettled([...PAGES, '/css/push.css?v=2', '/js/push.js?v=2'].map(async path => {
      const response = await fetch(path, { cache: 'reload' });
      if (response.ok) await cache.put(path, response);
    }));
    await self.skipWaiting();
  })());
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('giovannicerino-') && key !== CACHE_NAME) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});
self.addEventListener('fetch', event => {
  const request = event.request, url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin || request.headers.has('Authorization')) return;
  const publicAsset = PAGES.includes(url.pathname) ||
    ['/css/', '/js/', '/images/', '/vender/'].some(prefix => url.pathname.startsWith(prefix));
  if (!publicAsset) return;
  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    try {
      const response = await fetch(request, { cache: 'no-cache' });
      if (response.ok && !response.redirected && response.type === 'basic') await cache.put(request, response.clone());
      return response;
    } catch {
      return await cache.match(request) || new Response('Connessione assente. Riprova quando sei online.', {
        status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' },
      });
    }
  })());
});
self.addEventListener('push', event => {
  let payload = {};
  try { payload = event.data?.json() || {}; } catch { /* Generic notification fallback. */ }
  event.waitUntil(self.registration.showNotification(payload.title || 'Giovanni Cerino', {
    body: payload.body || 'Hai un aggiornamento sui tuoi appuntamenti.',
    icon: '/images/logo-192x192.png', tag: payload.tag || 'giovannicerino-notification',
    data: { url: '/my-bookings.html' },
  }));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil((async () => {
    const target = new URL('/my-bookings.html', self.location.origin).href;
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const existing = windows.find(client => client.url === target);
    if (existing) return existing.focus();
    return self.clients.openWindow(target);
  })());
});
