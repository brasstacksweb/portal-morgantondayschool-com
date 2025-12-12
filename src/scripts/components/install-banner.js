export default class InstallBanner extends HTMLElement {
    constructor() {
        super();
        const { activeClass = 'is-active' } = this.dataset;
        const trigger = this.querySelector('button');

        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', e => {
            e.preventDefault();
            deferredPrompt = e;
            this.classList.add(activeClass);
        });
        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            this.classList.remove(activeClass);
        });
        trigger.onclick = async () => {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.classList.remove(activeClass);
        };
    }
}
