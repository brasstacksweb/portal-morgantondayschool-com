import confetti from 'canvas-confetti';

import { actions, events } from '../events';

const PROGRESS_ENDPOINT = '/json/fund-progress';
const CONFIRM_ENDPOINT = '/titan-fund/confirm';

// Query params Stripe and the checkout controller hand back.
const PARAMS = ['donation', 'session_id', 'reason'];

const ERROR_MESSAGES = {
    amount: 'Please choose or enter a donation amount of at least $1.',
    closed: 'The Titan Fund is not accepting online gifts right now.',
    unavailable: 'We could not reach our payment processor. Please try again in a moment.',
};

const getErrorMessage = reason => ERROR_MESSAGES[reason] || ERROR_MESSAGES.unavailable;

export default class FundProgress extends HTMLElement {
    constructor() {
        super();

        const bar = this.querySelector('div[data-progress]');
        const csrf = this.querySelector('input[data-csrf]');
        const submit = this.querySelector('button[type="submit"]');

        const load = async () => {
            const res = await fetch(PROGRESS_ENDPOINT, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '', csrfToken = '' } = await res.json();

            // Replacing the markup outright is what replays the bar's fill
            // animation, so a refreshed total visibly climbs from zero.
            bar.innerHTML = markup;

            // The donate form is rendered inside the homepage cache, so it ships
            // with an empty token and a disabled submit. Both are filled in here,
            // the same way tl-form enables its own button.
            if (csrf && csrfToken) {
                csrf.value = csrfToken;

                if (submit) {
                    submit.removeAttribute('disabled');
                }
            }
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

        if (status === 'error') {
            events.emit(actions.loadModal, {
                markup: `<div class="donate-thanks"><h2>Sorry</h2><p>${getErrorMessage(params.get('reason'))}</p></div>`,
            });
        }

        // Drop the params so a refresh neither re-celebrates nor re-warns.
        // 'canceled' needs no message — the donor chose it.
        if (status) {
            const url = new URL(window.location.href);

            PARAMS.forEach(p => { url.searchParams.delete(p); });
            window.history.replaceState({}, '', url);
        }
    }
}
