import { quickHash } from '../utilities';

export default function Modal(el, {
    actions,
    events,
    refresh,
}) {
    const dialog = el.querySelector('dialog');
    const close = dialog.querySelector('button');
    const content = dialog.querySelector('section');

    let currentContent = '';

    function handleKeyup({ key }) {
        if (key === 'Escape') {
            events.emit(actions.closeModal);
        }
    }
    function handleOpenModal() {
        events.emit(actions.lockScroll);
        dialog.showModal();

        document.addEventListener('keyup', handleKeyup);
    }
    function handleCloseModal() {
        events.emit(actions.unlockScroll);
        dialog.close();

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
