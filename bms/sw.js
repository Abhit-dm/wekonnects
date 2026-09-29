// sw.js
const CACHE_NAME = 'wekonnects-cache-v1';
const urlsToCache = [
  '/',
  '/login.php',
  '/index.php',
  '/assets/css/style.css'
];

// Install the Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch from Cache first, then Network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        return response || fetch(event.request);
      })
  );
});