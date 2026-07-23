// Generic disclosure dialog: a trigger button opens a native <dialog>; a close
// button (or Escape, or a backdrop click) closes it. The dialog's contents are
// server-rendered, so forms inside keep their CSRF/hashed/recaptcha wiring.
export default class Dialog extends HTMLElement {
    constructor() {
        super();

        const dialog = this.querySelector('dialog');
        const trigger = this.querySelector(':scope > button');
        const close = dialog.querySelector('button');

        trigger.onclick = () => { dialog.showModal(); };
        close.onclick = () => { dialog.close(); };

        // Close when the backdrop (the dialog element itself) is clicked.
        dialog.onclick = e => {
            if (e.target === dialog) dialog.close();
        };
    }
}
