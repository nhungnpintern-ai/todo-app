const CACHE_NAME = "Nhung Daily Planner";
const FILES_TO_CACHE = [
  "./",
  "./index.html",
  "./manifest.json",
  "./images/2.png",
  "./images/1.jpg"
];

// Install
self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(FILES_TO_CACHE))
  );
});

// Fetch
self.addEventListener("fetch", (e) => {
  e.respondWith(
    caches.match(e.request).then(res => res || fetch(e.request))
  );
});