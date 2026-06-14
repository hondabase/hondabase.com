/* Hondabase service worker - Web Push only (no offline caching). */

self.addEventListener('push', (event) => {
    if (!event.data) return;
    let payload = {};
    try { payload = event.data.json(); } catch (e) { payload = { title: 'Hondabase', body: event.data.text() }; }

    const title = payload.title || 'Hondabase';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: payload.badge || '/favicon.ico',
        data: payload.data || {},
        tag: payload.tag || undefined,
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            for (const w of wins) {
                if ('focus' in w) { w.navigate(url); return w.focus(); }
            }
            return clients.openWindow(url);
        })
    );
});
