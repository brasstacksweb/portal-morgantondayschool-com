// Service Worker for Push Notifications
/* eslint-disable no-restricted-globals */
self.addEventListener('push', event => {
    console.log('Push event received:', event);
    if (!event.data) {
        return;
    }

    const {
        body,
        url,
        tag = 'notification',
        title,
    } = event.data.json();
    console.log('Push event data:', { body, url, tag, title });

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
    console.log('Notification click received:', event);
    event.notification.close();

    event.waitUntil(
        clients.openWindow(event.notification.data.url), // eslint-disable-line no-undef
    );
});
/* eslint-enable no-restricted-globals */
