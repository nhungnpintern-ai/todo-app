self.addEventListener('install', (e) => {
  console.log('Service Worker: Installed');
});

self.addEventListener('fetch', (e) => {
  // Giúp app load nhanh hơn
  e.respondWith(fetch(e.request));
});