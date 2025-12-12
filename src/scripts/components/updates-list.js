export default function UpdatesList(element, { events, actions }) {
    const updates = element.querySelectorAll('[data-update-id]');

    async function markAsRead(updateId, el, observer) {
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
                body: JSON.stringify({ updateId: parseInt(updateId, 10) }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Mark as read visually
                el.classList.add('read');
                observer.unobserve(el);

                // Trigger badge refresh
                events.emit('notification:refresh');
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    }

    // Set up intersection observer to mark as read when viewed
    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const { updateId } = entry.target.dataset;
                    if (updateId) {
                        markAsRead(updateId, entry.target);
                    }
                }
            });
        },
        { threshold: 0.5 },
    );

    updates
        .filter(u => !u.classList.contains('read'))
        .forEach(u => { observer.observe(u); });
}
