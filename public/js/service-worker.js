// Service Worker Minimalista per PWA
// Solo registrazione e fullscreen, niente offline

const CACHE_NAME = 'barberapp-v1';

// Installazione
self.addEventListener('install', (event) => {
  console.log('Service Worker installato');
  self.skipWaiting();
});

// Attivazione
self.addEventListener('activate', (event) => {
  console.log('Service Worker attivato');
  self.clients.claim();
});

// Fetch (se in futuro vuoi aggiungere caching)
self.addEventListener('fetch', (event) => {
  // Lasciamo che il browser gestisca normalmente le richieste
  // Niente offline, tutto passa al network
});
