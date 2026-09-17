export default class FundProgress extends HTMLElement {
    constructor() {
        super();

        const bar = this.querySelector('div[data-progress]');

        const init = async () => {
            const res = await fetch('/json/fund-progress', {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const { markup = '' } = await res.json();

            bar.innerHTML = markup;
        };

        init();
    }
}
