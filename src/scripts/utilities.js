export const cycleElementStates = (
    els,
    elStates,
    cycleFunction = (cur, total) => (cur + 1) % total,
) => elStates.map(([state, activeEl]) => {
    els[activeEl].classList.remove(state);
    activeEl = cycleFunction(activeEl, els.length);
    els[activeEl].classList.add(state);

    return [state, activeEl];
});

export const matchHeights = els => {
    els.forEach(el => { el.style.height = 'inherit'; });
    const max = Math.max(...[...els].map(el => el.offsetHeight));
    els.forEach(el => { el.style.height = `${max}px`; });
};

// This is a simple, *insecure* hash that's short, fast, and has no dependencies.
// For algorithmic use, where security isn't needed, it's way simpler than sha1 (and all its deps)
// or similar, and with a short, clean (base 36 alphanumeric) result.
// Loosely based on the Java version; see
// https://stackoverflow.com/questions/6122571/simple-non-secure-hash-function-for-javascript
/* eslint-disable no-bitwise */
export const quickHash = str => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash &= hash; // Convert to 32bit integer
    }
    return new Uint32Array([hash])[0].toString(36);
};
/* eslint-enable no-bitwise */

export const arrayBufferToBase64Url = arrayBuffer => {
    const uint8Array = new Uint8Array(arrayBuffer);
    let base64String = btoa(String.fromCharCode(...uint8Array));
    base64String = base64String
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');

    return base64String;
};

export const base64ToUint8Array = base64String => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
};

export const isIOS = () => ([
    'iPad Simulator',
    'iPhone Simulator',
    'iPod Simulator',
    'iPad',
    'iPhone',
    'iPod',
].includes(navigator.platform) || (navigator.userAgent.includes('Mac') && 'ontouchend' in document));

export const isSafari = () => /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

export const isInStandaloneMode = () => navigator.standalone === true || matchMedia('(display-mode: standalone)').matches;

export const loadScript = (src, cb) => {
    const script = document.createElement('script');
    const head = document.querySelector('head');

    script.src = src;
    script.onload = cb;
    head.appendChild(script);
};
