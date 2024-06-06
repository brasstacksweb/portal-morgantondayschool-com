/**
 * Emit event - wrapper around CustomEvent API
 * @param {string} handle - a string representing the name of the event
 * @param {object} payload - data to be passed via the event to listening functions
 * @param {EventTarget} target - target to emit/broadcast event to
 */
function emit(handle, payload, target = window) {
    const event = new CustomEvent(handle, { detail: payload });
    target.dispatchEvent(event);
}

/**
 * Listen for custom event and execute callback on EventTarget
 * @param {string} handle - a string representing the name of the event
 * @param {function} cb - function to call w/ event argument when event is emitted
 * @param {EventTarget} target - target to attach listener to
 */
function on(handle, cb, target = window) {
    target.addEventListener(handle, cb);
}

function pop({
    container = document.body,
    classMap = {},
    actions = {},
    cb = null,
}) {
    const events = { emit, on };

    function refresh(c = null) {
        if (c === null) {
            return;
        }
        pop({ container: c, classMap, actions, cb });
    }

    Object.entries(classMap).forEach(([name, component]) => {
        container.querySelectorAll(`[data-comp-name="${name}"]`).forEach(node => {
            component(node, {
                ...JSON.parse(node.getAttribute('data-comp-params')),
                actions,
                events,
                refresh,
            });
        });
    });

    if (cb) {
        cb({ events, refresh });
    }
}

export default pop;
