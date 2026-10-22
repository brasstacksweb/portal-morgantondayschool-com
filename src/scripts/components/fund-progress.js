import confetti from 'canvas-confetti';

import { actions, events } from '../events';

// Query params Stripe hands back on the return from Checkout.
const PARAMS = ['donation', 'session_id'];

/**
 * Fire confetti over the open modal. The modal is a <dialog> opened with
 * showModal(), which puts it in the browser's top layer — nothing outside it can
 * paint above it, whatever its z-index. So the confetti gets its own canvas
 * inside the dialog, which shares the top layer, and removes it when done.
 */
const celebrate = async () => {
    const dialog = document.querySelector('tl-modal dialog[open]');

    if (!dialog) return;

    const canvas = document.createElement('canvas');

    canvas.setAttribute('aria-hidden', 'true');
    dialog.append(canvas);

    const fire = confetti.create(canvas, { resize: true, disableForReducedMotion: true });

    await fire({
        particleCount: 140,
        spread: 75,
        origin: { y: 0.4 },
    });

    fire.reset();
    canvas.remove();
};

export default class FundProgress extends HTMLElement {
    constructor() {
        super();

        const load = async () => {
            const res = await fetch('/json/fund-progress', {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            this.querySelector('div[data-progress]').innerHTML = markup;
        };

        const confirmDonation = async sessionId => {
            const res = await fetch(`/titan-fund/confirm?session_id=${encodeURIComponent(sessionId)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            if (!markup) {
                return;
            }

            // loadModal opens the dialog synchronously, so it is open here.
            events.emit(actions.loadModal, { markup });
            celebrate();

            await load();
        };

        const params = new URLSearchParams(window.location.search);
        const status = params.get('donation');
        const sessionId = params.get('session_id');

        load();

        if (status === 'success' && sessionId) {
            confirmDonation(sessionId);
        }

        // Drop the params so a refresh does not re-celebrate.
        // 'canceled' needs no message — the donor chose it.
        if (status) {
            const url = new URL(window.location.href);

            PARAMS.forEach(p => { url.searchParams.delete(p); });
            window.history.replaceState({}, '', url);
        }
    }
}
