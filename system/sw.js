// Nice Nail — Service Worker
const CACHE = 'nicenail-v1';
const SHELL = ['assets/style.css','assets/app.js','assets/logo.png','assets/icon-192.png'];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL).catch(()=>{})));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));
  self.clients.claim();
});

// شبكة أولاً للصفحات (PHP)، كاش للملفات الثابتة فقط
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET') return;
  if (SHELL.some(p => url.pathname.endsWith(p.replace('assets/','assets/')))) {
    e.respondWith(caches.match(e.request).then(r => r || fetch(e.request)));
  }
});

// استقبال Push (بدون حمولة) → اجلب آخر إشعار واعرضه
self.addEventListener('push', e => {
  e.waitUntil((async () => {
    let title = 'Nice Nail', body = 'لديك تحديث جديد', link = 'index.php?page=inbox';
    try {
      const r = await fetch('api.php?do=last_notif', { credentials: 'include' });
      const j = await r.json();
      if (j && j.notif) { title = j.notif.title || title; body = j.notif.body || body; link = j.notif.link || link; }
    } catch (_) {}
    await self.registration.showNotification(title, {
      body, icon: 'assets/icon-192.png', badge: 'assets/icon-192.png',
      dir: 'rtl', lang: 'ar', tag: 'nicenail', renotify: true, data: { link }
    });
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const link = (e.notification.data && e.notification.data.link) || 'index.php?page=inbox';
  e.waitUntil(clients.matchAll({ type:'window', includeUncontrolled:true }).then(cl => {
    for (const c of cl) { if ('focus' in c) { c.navigate(link); return c.focus(); } }
    if (clients.openWindow) return clients.openWindow(link);
  }));
});
