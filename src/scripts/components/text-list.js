export default class TextList extends HTMLElement {
    constructor() {
        super();

        const {
            endpoint,
            paramName,
            loadingClass = 'is-loading',
        } = this.dataset;

        if (!endpoint || !paramName) {
            return;
        }

        let { prevParam, nextParam } = this.dataset;

        const current = this.querySelector('header > nav > span');
        const [
            prev,
            next,
        ] = this.querySelectorAll('header > nav > button');

        const page = async (param, dir = 0) => {
            this.setAttribute('data-dir', dir);
            this.classList.add(loadingClass);
            prev.disabled = true;
            next.disabled = true;

            const res = await fetch(`${endpoint}?${new URLSearchParams({
                [paramName]: param,
            })}`, { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                return;
            }

            const {
                markup = '',
                prevParam: p = '',
                nextParam: n = '',
                current: c = '',
            } = await res.json();

            this.classList.remove(loadingClass);
            prev.disabled = false;
            next.disabled = false;
            prevParam = p;
            nextParam = n;
            current.innerHTML = c;
            this.querySelector('ul').outerHTML = markup.trim();
        };

        prev.onclick = () => { page(prevParam, -1); };
        next.onclick = () => { page(nextParam, 1); };
    }
}
