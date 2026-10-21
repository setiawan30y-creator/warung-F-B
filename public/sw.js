/* ALMARA PWA service worker.
 * Hanya aset statis yang di-cache. Halaman dan data selalu diambil dari server,
 * karena transaksi baru dianggap sah setelah server mengonfirmasi (Blueprint §3.3). */
const CACHE = 'almara-static-v2';
const ASSETS = ['/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) =>
    Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
  ).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  const isStatic = /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/.test(url.pathname)
    && !url.pathname.startsWith('/livewire') && !url.pathname.startsWith('/storage');
  if (!isStatic) return; // halaman & API: langsung ke jaringan

  e.respondWith(
    caches.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
        return res;
      }).catch(() => hit);
      return hit || net;
    })
  );
});
