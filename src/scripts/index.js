// Framework
import pop from './components';

// Components
// import Form from './components/form';
// import FormField from './components/form-field';
import TextList from './components/text-list';

// Define map of component handles to component classes
const classMap = {
    // form: Form,
    // 'form-field': FormField,
    'text-list': TextList,
};

// Define all actions/commands that components pub/sub
const actions = {
    lockScroll: 'lock-scroll',
    unlockScroll: 'unlock-scroll',
    showFieldError: 'show-field-error',
};

// Event handler functions
function handleDOMConentLoaded() {
    function cb() {
        // Set header height CSS variable
        const header = document.querySelector('.header');
        document.querySelector(':root').style.setProperty('--h-header', `${header.offsetHeight}px`);

        // Smooth scroll anchors
        document.body.addEventListener('click', e => {
            const link = e.target.closest('a');

            if (!link) return;

            if (link.href.includes('#') && link.target !== '_blank') {
                e.preventDefault();

                const target = document.getElementById(link.href.split('#')[1]);

                if (target) {
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
        const loadModal = string => {
            const dialog = document.querySelector('body > dialog');

            dialog.querySelector('section').innerHTML = string;
            dialog.showModal();
        };
        document.querySelector('body > dialog > button').onclick = ({ currentTarget }) => { currentTarget.parentElement.close(); };
        document.querySelectorAll('img[data-big-url]').forEach(image => {
            image.onclick = () => { loadModal(`<img src="${image.dataset.bigUrl}" alt="${image.alt}" />`); };
        });
    }

    pop({ classMap, actions, cb });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', handleDOMConentLoaded);
} else {
    handleDOMConentLoaded();
}
