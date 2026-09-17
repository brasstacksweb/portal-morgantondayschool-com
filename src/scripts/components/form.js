import { actions, events } from '../events';

export default class Form extends HTMLElement {
    constructor() {
        super();

        const { siteKey = '', redirectPath = '' } = this.dataset;

        const errorMessage = this.querySelector('p');
        const form = this.querySelector('form');
        const submit = form.querySelector('[type="submit"]');
        const successMessage = this.querySelector('form + div');

        submit.removeAttribute('disabled');

        form.onsubmit = async e => {
            e.preventDefault();

            submit.setAttribute('disabled', 'true');

            const body = new FormData(form);
            const token = await grecaptcha.enterprise.execute(siteKey, { action: 'submit' }); // eslint-disable-line no-undef

            body.append('token', token);

            const res = await fetch('/', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body,
            });
            const { message = '', errors = {}, redirect = '' } = await res.json();

            // Reset all errrors to empty
            errorMessage.textContent = '';
            Array.from(body.keys()).map(name => name.replace('[]', '')).forEach(name => {
                events.emit(actions.showFieldError, { name, errors: [] });
            });
            submit.removeAttribute('disabled');

            switch (res.status) {
            case 500:
                window.alert(message); // eslint-disable-line no-alert

                break;
            case 400:
                errorMessage.textContent = message;
                Object.entries(errors).forEach(([name, errs]) => {
                    events.emit(actions.showFieldError, { name, errors: errs });
                });

                break;
            case 200:
            default: {
                // A redirect chosen by the server (e.g. an off-site payment page)
                // wins over the one the template declared.
                const destination = redirect || redirectPath;

                if (destination) {
                    // A redirect to the current page (e.g. a '#notice' hash) is
                    // a same-document navigation — href only scrolls, so force a
                    // reload to reflect the new server state. A different page
                    // navigates (and reloads) on its own.
                    const target = new URL(destination, window.location.href);
                    const samePage = target.origin === window.location.origin
                        && target.pathname === window.location.pathname
                        && target.search === window.location.search;

                    window.location.href = destination;
                    if (samePage) window.location.reload();

                    return;
                }

                form.remove();
                successMessage.style.display = 'block';
                this.parentElement.style.scrollMarginTop = 'var(--h-header)';
                this.parentElement.scrollIntoView({ behavior: 'smooth' });
            }
            }
        };
    }
}
