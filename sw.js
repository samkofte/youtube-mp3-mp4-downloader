const CACHE_NAME = 'yt-streamer-v4';
const urlsToCache = [
  '/',
  '/index.html',
  '/css/style.css',
  '/js/api_core.js',
  '/js/app_core.js',
  '/js/ui_core.js',
  '/manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  if (event.request.url.includes('/api/')) {
      // Don't cache API calls in service worker, use our memory cache on the backend
      return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});
