export default function HomeDashboard(el, {
    prevParam,
    nextParam,
    endpoint,
    paramName,
    loadingClass = 'is-loading',
}) {
    const current = el.querySelector('header > nav > span');
    const [
        prev,
        next,
    ] = el.querySelectorAll('header > nav > button');

    function page(param, dir = 0) {
        // el.scrollIntoView();
        el.setAttribute('data-dir', dir);
        el.classList.add(loadingClass);
        prev.disabled = true;
        next.disabled = true;

        fetch(`${endpoint}?${new URLSearchParams({
            [paramName]: param,
        })}`, {
            headers: { Accept: 'application/json' },
        })
            .then(res => res.json().then(json => ({
                status: res.status,
                ...json,
            })))
            .then(({
                status,
                markup = '',
                prevParam: p = '',
                nextParam: n = '',
                current: c = '',
            }) => {
                if (status !== 200) return;

                el.classList.remove(loadingClass);
                prev.disabled = false;
                next.disabled = false;
                prevParam = p;
                nextParam = n;
                current.innerHTML = c;
                el.querySelector('ul').outerHTML = markup.trim();
            });
    }

    prev.onclick = () => { page(prevParam, -1); };
    next.onclick = () => { page(nextParam, 1); };
}
