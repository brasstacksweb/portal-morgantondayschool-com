import { actions, events } from '../events';

const toggleVisibility = (el, name, visible) => {
    if (visible) {
        el.querySelectorAll(`[name="${name}"]`).forEach(f => f.setAttribute('required', 'true'));
        el.style.display = 'block';
        el.parentElement.style.display = 'block';
    } else {
        el.querySelectorAll(`[name="${name}"]`).forEach(f => f.removeAttribute('required'));
        el.style.display = 'none';
        // Hide parent container for form column formatting
        el.parentElement.style.display = 'none';
    }
};

export default class FormField extends HTMLElement {
    constructor(el) {
        super();

        const {
            name,
            errorClass,
            conditionalName,
            conditionalValue,
        } = this.dataset;

        const error = this.querySelector('p');

        events.on(actions.showFieldError, ({ detail }) => {
            if (detail.name !== name) return;

            this.classList.toggle(errorClass, detail.errors.length > 0);
            error.textContent = detail.errors.join(', ');
        });

        // Initalize conditional formatting
        if (conditionalName && conditionalValue) {
            const form = this.closest('form');
            const targets = form.querySelectorAll(`[name="${conditionalName}"]`);
            const formData = new FormData(form);

            targets.forEach(target => {
                target.addEventListener('change', e => {
                    toggleVisibility(el, name, e.currentTarget.value === conditionalValue);
                });
            });

            toggleVisibility(el, name, formData.get(conditionalName) === conditionalValue);
        }
    }
}
