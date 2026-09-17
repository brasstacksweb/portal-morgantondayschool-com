import confetti from 'canvas-confetti';

import { actions, events } from '../events';

const PROGRESS_ENDPOINT = '/json/fund-progress';
const CONFIRM_ENDPOINT = '/titan-fund/confirm';

// Query params Stripe hands back on the return from Checkout.
const PARAMS = ['donation', 'session_id'];

export default class FundProgress extends HTMLElement {
    constructor() {
        super();

        const bar = this.querySelector('div[data-progress]');

        const load = async () => {
            const res = await fetch(PROGRESS_ENDPOINT, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            // Replacing the markup outright is what replays the bar's fill
            // animation, so a refreshed total visibly climbs from zero.
            bar.innerHTML = markup;
        };

        const confirmDonation = async sessionId => {
            const res = await fetch(`${CONFIRM_ENDPOINT}?session_id=${encodeURIComponent(sessionId)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            if (!markup) {
                return;
            }

            events.emit(actions.loadModal, { markup });
            confetti({
                particleCount: 140,
                spread: 75,
                origin: { y: 0.4 },
                disableForReducedMotion: true,
            });

            // Re-read the totals so the donor's own gift is in the bar behind
            // the confetti.
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
