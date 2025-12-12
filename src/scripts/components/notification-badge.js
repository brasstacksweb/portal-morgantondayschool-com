export default function NotificationBadge(element, { events, actions }) {
    const badge = element.querySelector('.badge');
    let refreshInterval;

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count.toString();
            badge.style.display = 'inline-block';
            badge.setAttribute('aria-label', `${count} unread notifications`);
        } else {
            badge.style.display = 'none';
            badge.removeAttribute('aria-label');
        }
    }

    async function fetchUnreadCount() {
        try {
            const response = await fetch('/registration/notifications/unread-count', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            updateBadge(data.count || 0);
        } catch (error) {
            console.error('Failed to fetch unread count:', error);
        }
    }

    fetchUnreadCount();

    // Refresh every 60 seconds
    refreshInterval = setInterval(fetchUnreadCount, 60000);

    // Listen for manual refresh events
    events.on('notification:refresh', fetchUnreadCount);
}
