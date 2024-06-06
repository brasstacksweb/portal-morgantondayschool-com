export default function CardsListing(el, {
    cardsContHandle,
    paginationLinksContHandle,
    labels,
    section,
    notIds,
    activeClass = 'is-active',
}) {
    const nav = el.querySelector('nav');
    const filtersToggle = nav.querySelector('[type="checkbox"]');
    const filtersLabel = nav.querySelector('label > span');
    const filters = nav.querySelectorAll('ul > li > a');
    const count = nav.querySelector('em');
    const featuredArticle = el.querySelector('article');
    const cardsCont = el.querySelector(cardsContHandle);
    const paginationLinksCont = el.querySelector(paginationLinksContHandle);

    let activeFilterIndex = [...filters].findIndex(f => f.classList.contains(activeClass));

    function updateCards(url, push = true) {
        el.setAttribute('aria-busy', 'true');

        fetch(`/json/cards-listing?${new URLSearchParams({
            section,
            notIds,
        })}&${url.split('?')[1] || ''}`, {
            headers: { Accept: 'application/json' },
        })
            .then(res => res.json().then(json => ({
                status: res.status,
                ...json,
            })))
            .then(({
                status,
                info = null,
                cardsMarkup = '',
                paginationLinksMarkup = '',
            }) => {
                if (status !== 200) return;

                const activeFilter = filters[activeFilterIndex];
                const { first, last, total, currentPage } = info;
                const [s, p] = labels;
                const showFeaturedArticle = currentPage === 1 && activeFilterIndex === 0;
                const realTotal = showFeaturedArticle ? total + 1 : total;
                const realLast = showFeaturedArticle && total === last ? realTotal : last;

                el.removeAttribute('aria-busy');
                filtersToggle.checked = false;
                filtersLabel.textContent = activeFilter.textContent;
                filters.forEach(f => { f.classList.toggle(activeClass, f === activeFilter); });
                count.innerHTML = `${first} to ${realLast} of ${realTotal} ${realTotal === 1 ? s : p}`;
                featuredArticle.setAttribute('aria-hidden', !showFeaturedArticle);
                cardsCont.innerHTML = cardsMarkup;
                paginationLinksCont.innerHTML = paginationLinksMarkup;
                nav.scrollIntoView({ behavior: 'smooth' });
                if (push) {
                    window.history.pushState({ activeFilterIndex }, '', url);
                }
            });
    }

    window.addEventListener('popstate', ({ state }) => {
        activeFilterIndex = state.activeFilterIndex;

        updateCards(window.location.href, false);
    });
    // Event delegation bc links are dynamically updated
    document.body.addEventListener('click', e => {
        if (paginationLinksCont.contains(e.target)) {
            e.preventDefault();

            const link = e.target.closest('a'); // Target could be a SVG icon inside a link

            updateCards(link.href);
        }
    });
    filters.forEach((f, i) => {
        f.onclick = e => {
            e.preventDefault();

            activeFilterIndex = i;

            updateCards(f.href);
        };
    });

    window.history.replaceState({ activeFilterIndex }, '', window.location.href);
}
