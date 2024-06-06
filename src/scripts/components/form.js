export default function Form(el, {
    siteKey,
    actions,
    events,
}) {
    const form = el.querySelector('form');
    const submit = form.querySelector('[type="submit"]');
    const successMessage = el.querySelector('form + div');
    const grc = grecaptcha; // eslint-disable-line no-undef

    submit.removeAttribute('disabled');

    form.onsubmit = e => {
        e.preventDefault();

        submit.setAttribute('disabled', 'true');

        const body = new FormData(form);

        grc.ready(() => {
            grc.execute(siteKey, { action: 'submit' }).then(token => {
                body.append('token', token);

                fetch('/', {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body,
                })
                    .then(res => res.json().then(json => ({
                        status: res.status,
                        ...json,
                    })))
                    .then(({
                        status,
                        message = '',
                        errors = {},
                    }) => {
                        // Reset all field errros to empty
                        Array.from(body.keys()).map(name => name.replace('[]', '')).forEach(name => {
                            events.emit(actions.showFieldError, { name, errors: [] });
                        });
                        submit.removeAttribute('disabled');

                        switch (status) {
                        case 500:
                            window.alert(message); // eslint-disable-line no-alert

                            break;
                        case 400:
                            Object.entries(errors).forEach(([name, errs]) => {
                                events.emit(actions.showFieldError, { name, errors: errs });
                            });

                            break;
                        case 200:
                        default:
                            /* eslint-disable no-undef */
                            if (typeof dataLayer === 'object') {
                                dataLayer.push({ event: 'form_submit' });
                            }
                            /* eslint-enable no-undef */

                            form.remove();
                            successMessage.style.display = 'block';
                            el.parentElement.style.scrollMarginTop = 'var(--h-header)';
                            el.parentElement.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
            });
        });
    };
}
