import { Controller } from '@hotwired/stimulus';

/*
 * Controla a apertura/peche do sidebar en móbil (menú hamburguesa + overlay).
 * En escritorio o sidebar é sempre visible; este controller só actúa por baixo
 * do breakpoint "lg" de Tailwind (1024px).
 */
export default class extends Controller {
    static targets = ['panel', 'overlay'];

    open() {
        this.panelTarget.classList.remove('-translate-x-full');
        this.overlayTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        if (this.panelTarget.classList.contains('-translate-x-full')) {
            this.open();
        } else {
            this.close();
        }
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    closeOnDesktop() {
        if (window.innerWidth >= 1024) {
            this.close();
        }
    }
}
