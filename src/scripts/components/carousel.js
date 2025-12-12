export default class Carousel extends HTMLElement {
    constructor(el) {
        super();

        const carousel = this.querySelector('ul');
        const [prev, next] = this.querySelectorAll('ul + nav > button');

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
}
