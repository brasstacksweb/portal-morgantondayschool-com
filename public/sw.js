// Service Worker for Push Notifications
/* eslint-disable no-restricted-globals */
self.addEventListener('push', event => {
    if (!event.data) {
        return;
    }

    const {
        body,
        url,
        tag = 'notification',
        title,
    } = event.data.json();

    const options = {
        body,
        icon: '/android-chrome-192x192.png',
        data: { url },
        requireInteraction: false,
        tag,
    };

    event.waitUntil(
        self.registration.showNotification(title, options),
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    event.waitUntil(
        clients.openWindow(event.notification.data.url), // eslint-disable-line no-undef
    );
});
/* eslint-enable no-restricted-globals */
