export default function Form(el) {
    const carousel = el.querySelector('ul');
    const [prev, next] = el.querySelectorAll('ul + nav > button');

    prev.onclick = () => {
        carousel.scrollBy({
            left: -1 * carousel.firstElementChild.offsetWidth,
            behavior: 'smooth',
        });
    };
    next.onclick = () => {
        carousel.scrollBy({
            left: carousel.firstElementChild.offsetWidth,
            behavior: 'smooth',
        });
    };
}
