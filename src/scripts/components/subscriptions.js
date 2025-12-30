import {
    arrayBufferToBase64Url,
    base64ToUint8Array,
    isAndroid as checkAndroid,
    isIOS as checkIOS,
    isPushNotificationSupported,
    requiresInstallForNotifications,
} from '../utilities';

const getUnsupportedMessage = () => 'Push notifications are not supported by your browser.';

/* eslint-disable no-nested-ternary */
const getDeniedMessage = (isAndroid, isIOS) => (isAndroid
    ? 'Notifications are blocked. Tap the 🔒 icon in your address bar to enable them.'
    : isIOS
        ? 'Notifications are blocked. Go to Settings > Safari > Website Settings to enable them.'
        : 'You have blocked notifications. Please enable them in your browser settings.');
/* eslint-enable no-nested-ternary */

const getDefaultMessage = isAndroid => (isAndroid
    ? 'Tap below to enable push notifications and get updates about your classes.'
    : 'Click below to enable notifications in your browser and subscribe to class updates.');

const getUnsubscribeMessage = () => 'You have unsubscribed from notifications. Click below to subscribe again.';

const getIOSInstallMessage = () => `
To enable notifications on iOS Safari:
1. Tap the Share button (⬆️)
2. Scroll down and tap "Add to Home Screen"
3. Open the app from your home Screen
4. Return here to enable notifications`;

const getErrorMessage = () => 'There was an error enabling notifications. Please try again later.';

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

        // Detect device capabilities once
        const [isAndroid, isIOS] = [checkAndroid(), checkIOS()];

        // Check basic push notification support
        if (!isPushNotificationSupported()) {
            body.textContent = getUnsupportedMessage();
            this.classList.add(loadedClass);
            return;
        }

        // Handle iOS Safari special case - requires "Add to Home Screen"
        if (requiresInstallForNotifications()) {
            body.innerHTML = getIOSInstallMessage().replace(/\n/g, '<br>');
            subscribeTrigger.style.display = 'none';
            this.classList.add(loadedClass);
            return;
        }

        const { permission } = Notification;

        switch (permission) {
        case 'granted':
            if (localStorage.getItem('unsubscribed') === 'true') {
                this.classList.remove(activeClass);
                body.textContent = getUnsubscribeMessage();
                break;
            }
            this.subscribe(subscribeForm, vapidPublicKey, activeClass);
            break;
        case 'denied':
            this.classList.remove(activeClass);
            body.textContent = getDeniedMessage(isAndroid, isIOS);
            subscribeTrigger.style.display = 'none';
            break;
        case 'default':
            this.classList.remove(activeClass);
            body.textContent = getDefaultMessage(isAndroid);
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
                body.textContent = getErrorMessage();
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
