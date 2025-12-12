// Define all actions/commands that components pub/sub
export const actions = {
    lockScroll: 'lock-scroll',
    unlockScroll: 'unlock-scroll',
    openModal: 'open-modal',
    closeModal: 'close-modal',
    loadModal: 'load-modal',
    showFieldError: 'show-field-error',
};

/**
 * Emit event - wrapper around CustomEvent API
 * @param {string} handle - a string representing the name of the event
 * @param {object} payload - data to be passed via the event to listening functions
 * @param {EventTarget} target - target to emit/broadcast event to
 */
const emit = (handle, payload, target = window) => {
    const event = new CustomEvent(handle, { detail: payload });
    target.dispatchEvent(event);
};

/**
 * Listen for custom event and execute callback on EventTarget
 * @param {string} handle - a string representing the name of the event
 * @param {function} cb - function to call w/ event argument when event is emitted
 * @param {EventTarget} target - target to attach listener to
 */
const on = (handle, cb, target = window) => {
    target.addEventListener(handle, cb);
};

export const events = { emit, on };
