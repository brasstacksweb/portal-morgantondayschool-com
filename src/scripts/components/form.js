export default function Form(el, {
    siteKey,
    redirectPath,
    actions,
    events,
}) {
    const errorMessage = el.querySelector('p');
    const form = el.querySelector('form');
    const submit = form.querySelector('[type="submit"]');
    const successMessage = el.querySelector('form + div');

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
        const { message = '', errors = {} } = await res.json();

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
        default:
            if (redirectPath) {
                window.location.href = redirectPath;

                return;
            }

            form.remove();
            successMessage.style.display = 'block';
            el.parentElement.style.scrollMarginTop = 'var(--h-header)';
            el.parentElement.scrollIntoView({ behavior: 'smooth' });
        }
    };
}
