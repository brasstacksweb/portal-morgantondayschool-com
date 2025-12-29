import { arrayBufferToBase64Url, base64ToUint8Array } from '../utilities';

export default class Subscriptions extends HTMLElement {
    constructor() {
        super();
        const {
            loadedClass = 'has-loaded',
            activeClass = 'is-active',
            vapidPublicKey,
        } = this.dataset;
        const [subscribeForm, unsubscribeForm] = this.querySelectorAll('form');
        const body = this.querySelector('p');
        const [subscribeTrigger, unsubscribeTrigger] = this.querySelectorAll('button[type="button"]');

        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            body.textContent = 'Push notifications are not supported by your browser.';
            this.classList.add(loadedClass);

            return;
        }

        const { permission } = Notification;

        switch (permission) {
        case 'granted':
            const unsubscribed = localStorage.getItem('unsubscribed');
            if (unsubscribed === 'true') {
                this.classList.remove(activeClass);
                body.textContent = 'You have unsubscribed from notifications. Click below to subscribe again.';
                break;
            }
            this.subscribe(subscribeForm, vapidPublicKey, activeClass);
            break;
        case 'denied':
            this.classList.remove(activeClass);
            body.textContent = 'You have blocked notifications. Please enable them in your browser settings.';
            subscribeTrigger.style.display = 'none';
            break;
        case 'default':
            this.classList.remove(activeClass);
            body.textContent = 'Click below to enable notifications in your browser and subscribe to class updates.';
            break;
        default:
            this.classList.remove(activeClass);
            break;
        }

        subscribeTrigger.onclick = async () => {
            try {
                localStorage.removeItem('unsubscribed');
                await Notification.requestPermission();
                window.location.reload();
            } catch (error) {
                console.error('Error requesting notification permission:', error);
            }
        };
        unsubscribeTrigger.onclick = async () => {
            await this.unsubscribe(unsubscribeForm);
        };

        this.classList.add(loadedClass);
    }

    async subscribe(form, vapidPublicKey, activeClass) {
        try {
            let registration = await navigator.serviceWorker.getRegistration();
            if (!registration) {
                registration = await navigator.serviceWorker.register('/sw.js');

                await navigator.serviceWorker.ready;
            }

            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                this.classList.add(activeClass);

                return;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64ToUint8Array(vapidPublicKey),
            });
            const formData = new FormData(form);

            formData.append('endpoint', subscription.endpoint);
            formData.append('p256dhKey', arrayBufferToBase64Url(subscription.getKey('p256dh')));
            formData.append('authKey', arrayBufferToBase64Url(subscription.getKey('auth')));

            const res = await fetch('/', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
            });

            if (res.ok) {
                this.classList.add(activeClass);
                this.scrollIntoView({ behavior: 'smooth' });
            } else {
                this.unsubscribe();

                throw new Error('Failed to save subscription');
            }
        } catch (error) {
            console.error('Error enabling notifications:', error);
        }
    }

    async unsubscribe(form) {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await subscription.unsubscribe();
            }

            if (!form) {
                return;
            }

            const formData = new FormData(form);

            formData.append('endpoint', subscription ? subscription.endpoint : '');

            const res = await fetch('/', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
            });

            if (res.ok) {
                localStorage.setItem('unsubscribed', 'true');
                window.location.reload();
            } else {
                throw new Error('Failed to remove subscription from server');
            }
        } catch (error) {
            console.error('Error disabling notifications:', error);
        }
    }
}
