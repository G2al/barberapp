const CACHE_NAME = 'mottolasfamily-admin-v1';

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.add('/admin/manifest.json');
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('mottolasfamily-admin-') && key !== CACHE_NAME) {
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

  // Admin pages and resources stay network-first and are never cached.
  event.respondWith(fetch(request));
});
