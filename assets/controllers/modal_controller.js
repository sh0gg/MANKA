import { Controller } from '@hotwired/stimulus';

/*
 * Modal xenérico de confirmación/diálogo.
 * Uso: <div data-controller="modal"> ... <button data-action="modal#open">Abrir</button>
 *      <div data-modal-target="dialog" class="hidden"> ... <button data-action="modal#close">Cancelar</button> ... </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['dialog'];

    open() {
        this.dialogTarget.classList.remove('hidden');
        this.dialogTarget.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.dialogTarget.classList.add('hidden');
        this.dialogTarget.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    closeOnOverlayClick(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
