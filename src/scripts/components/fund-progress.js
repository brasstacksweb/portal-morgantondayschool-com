import confetti from 'canvas-confetti';

import { actions, events } from '../events';

// Query params Stripe hands back on the return from Checkout.
const PARAMS = ['donation', 'session_id'];

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

            events.emit(actions.loadModal, { markup });
            confetti({
                particleCount: 140,
                spread: 75,
                origin: { y: 0.4 },
                disableForReducedMotion: true,
            });

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
