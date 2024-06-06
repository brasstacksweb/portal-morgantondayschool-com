import { quickHash } from '../utilities';

export default function Modal(el, {
    activeClass = 'is-active',
    actions,
    events,
    refresh,
}) {
    const close = el.querySelector('button');
    const content = el.querySelector('div');

    let currentContent = '';

    // Event handler functions
    function handleKeyup({ key }) {
        if (key === 'Escape') {
            events.emit(actions.closeModal);
        }
    }

    function handleOpenModal() {
        events.emit(actions.lockScroll);
        el.classList.add(activeClass);

        document.addEventListener('keyup', handleKeyup);
    }
    function handleCloseModal() {
        events.emit(actions.unlockScroll);
        el.classList.remove(activeClass);

        document.removeEventListener('keyup', handleKeyup);
    }
    function handleLoadModal(e) {
        const {
            markup,
        } = e.detail;

        const newContent = quickHash(markup);

        if (newContent !== currentContent) {
            currentContent = newContent;
            content.innerHTML = markup;
            refresh(content);
        }
        events.emit(actions.openModal);
    }

    // Add event listeners
    events.on(actions.openModal, handleOpenModal);
    events.on(actions.closeModal, handleCloseModal);
    events.on(actions.loadModal, handleLoadModal);
    close.onclick = () => { events.emit(actions.closeModal); };
}
