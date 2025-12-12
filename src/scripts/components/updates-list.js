export default function UpdatesList(element, { events, actions }) {
    const updateItems = element.querySelectorAll('[data-update-id]');
    let observer;

    function handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const { updateId } = entry.target.dataset;
                if (updateId) {
                    markAsRead(updateId, entry.target);
                }
            }
        });
    }

    async function markAsRead(updateId, element) {
        try {
            const csrfToken = document.querySelector('[name="CRAFT_CSRF_TOKEN"]');
            if (!csrfToken) {
                console.warn('CSRF token not found');
                return;
            }

            const response = await fetch('/registration/notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken.value,
                },
                body: JSON.stringify({ updateId: parseInt(updateId) }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Mark as read visually
                element.classList.add('read');
                observer.unobserve(element);

                // Trigger badge refresh
                events.emit('notification:refresh');
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    }

    function cleanup() {
        if (observer) {
            observer.disconnect();
        }
    }

    // Set up intersection observer to mark as read when viewed
    observer = new IntersectionObserver(
        handleIntersection,
        {
            threshold: 0.5,
            rootMargin: '0px',
        },
    );

    updateItems.forEach(item => {
        if (!item.classList.contains('read')) {
            observer.observe(item);
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', cleanup);
}
