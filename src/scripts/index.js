// Framework
import pop from './components';

// Components
import CardsListing from './components/cards-listing';
import Form from './components/form';
import FormField from './components/form-field';
import Modal from './components/modal';
import YoutubeVideo from './components/youtube-video';

// Define map of component handles to component classes
const classMap = {
    'cards-listing': CardsListing,
    form: Form,
    'form-field': FormField,
    modal: Modal,
    'youtube-video': YoutubeVideo,
};

// Define all actions/commands that components pub/sub
const actions = {
    // Action events
    lockScroll: 'lock-scroll',
    unlockScroll: 'unlock-scroll',
    openModal: 'open-modal',
    closeModal: 'close-modal',
    loadModal: 'load-modal',
    showFieldError: 'show-field-error',
};

// Event handler functions
function handleDOMConentLoaded() {
    function cb({ events }) {
        document.body.addEventListener('click', e => {
            const link = e.target.closest('a');

            if (!link) return;

            // Anchors
            if (link.matches('[href="#"]')) {
                e.preventDefault();

                const target = document.querySelector(link.href);

                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }

            // Videos
            if (link.matches('[href*="youtube.com"]')) {
                e.preventDefault();

                const videoId = link.href.split('v=')[1];

                fetch(`/json/youtube-video?${new URLSearchParams({ videoId })}`, {
                    headers: { Accept: 'application/json' },
                })
                    .then(res => res.json().then(json => ({
                        status: res.status,
                        ...json,
                    })))
                    .then(({
                        status,
                        markup = '',
                    }) => {
                        switch (status) {
                        case 500:
                            break;
                        case 400:
                            break;
                        case 200:
                        default:
                            events.emit(actions.loadModal, { markup });
                        }
                    });
            }
        });

        if (window.location.hash) {
            const target = document.querySelector(window.location.hash);

            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Only play videos when they are in view for scroll performance
        const videoObserver = new IntersectionObserver(entries => {
            entries.forEach(({ target, isIntersecting }) => {
                if (isIntersecting) {
                    target.play();
                } else {
                    target.pause();
                }
            });
        }, { threshold: 0.1 });
        Array.from(document.querySelectorAll('video'))
            .filter(v => v.hasAttribute('playsinline'))
            .forEach(v => { videoObserver.observe(v); });
    }

    pop({ classMap, actions, cb });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', handleDOMConentLoaded);
} else {
    handleDOMConentLoaded();
}
