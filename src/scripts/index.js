import { actions, events } from './events';

// Components
import Form from './components/form';
import FormField from './components/form-field';
import Carousel from './components/carousel';
import InstallBanner from './components/install-banner';
import Modal from './components/modal';
import TextList from './components/text-list';
// Registration components
// import NotificationBadge from './components/notification-badge';
// import UpdatesList from './components/updates-list';

// Event handler functions
function handleDOMConentLoaded() {
    customElements.define('tl-carousel', Carousel);
    customElements.define('tl-form', Form);
    customElements.define('tl-form-field', FormField);
    customElements.define('tl-install-banner', InstallBanner);
    customElements.define('tl-modal', Modal);
    customElements.define('tl-text-list', TextList);

    // Set header height CSS variable
    const header = document.querySelector('.header');
    document.querySelector(':root').style.setProperty('--h-header', `${header.offsetHeight}px`);

    // Smooth scroll anchors
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');

        if (!link) return;

        if (link.href.includes('#') && link.target !== '_blank') {
            const target = document.getElementById(link.href.split('#')[1]);

            if (target) {
                e.preventDefault();
                header.querySelector('[type="checkbox"]#nav-toggle').checked = false;
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

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

    // Load bigable images in lightbox
    document.querySelectorAll('img[data-big-url]').forEach(image => {
        image.onclick = () => {
            const markup = `<img src="${image.dataset.bigUrl}" alt="${image.alt}" />`;

            events.emit(actions.loadModal, { markup });
        };
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', handleDOMConentLoaded);
} else {
    handleDOMConentLoaded();
}
