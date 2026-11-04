import confetti from 'canvas-confetti';

import { actions, events } from '../events';

// Query params Stripe hands back on the return from Checkout.
const PARAMS = ['donation', 'session_id'];

// A page can now have more than one tl-fund-progress — the homepage/activity
// ticker and any number of Campaign CTAs. All of them watch the same return-
// from-Checkout query params, but only one should act on them: module-level
// state (shared by every instance on the page, reset on each fresh page load)
// lets the first one claim it so confirmDonation and the confetti fire once,
// not once per instance.
let handledReturn = false;

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
            const progress = this.querySelector('div[data-progress]');

            if (!progress) {
                return;
            }

            const res = await fetch('/json/fund-progress', {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            progress.innerHTML = markup;
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

        // Claimed synchronously, before anything async starts, so whichever
        // instance's constructor runs first on this page (DOM order) is the
        // one that handles the return — every later instance sees it already
        // claimed and does neither the confirm nor the param cleanup below.
        const handlingReturn = Boolean(status) && !handledReturn;

        if (handlingReturn) {
            handledReturn = true;
        }

        load();

        if (handlingReturn && status === 'success' && sessionId) {
            confirmDonation(sessionId);
        }

        // Drop the params so a refresh does not re-celebrate.
        // 'canceled' needs no message — the donor chose it.
        if (handlingReturn) {
            const url = new URL(window.location.href);

            PARAMS.forEach(p => { url.searchParams.delete(p); });
            window.history.replaceState({}, '', url);
        }
    }
}
