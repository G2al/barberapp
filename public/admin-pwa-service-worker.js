const CACHE_NAME = 'gabriele-del-piano-admin-v1';

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.put('/admin/manifest.json', await fetch('/admin/manifest.json', { cache: 'no-cache' }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('gabriele-del-piano-admin-') && key !== CACHE_NAME) {
        await caches.delete(key);
      }
    }
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin ||
      request.headers.has('Authorization') || !url.pathname.startsWith('/admin')) {
    return;
  }

  // Keep Filament pages and resources online; never cache admin data.
  event.respondWith(fetch(request));
});
