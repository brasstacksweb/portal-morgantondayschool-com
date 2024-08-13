export default function InstallBanner(el, {
    activeClass = 'is-active',
}) {
    const trigger = el.querySelector('button');

    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        deferredPrompt = e;
        el.classList.add(activeClass);
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        el.classList.remove(activeClass);
    });
    trigger.onclick = async () => {
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        el.classList.remove(activeClass);
    };

    el.classList.add(activeClass);
}
