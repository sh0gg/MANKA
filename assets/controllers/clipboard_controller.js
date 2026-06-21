import { Controller } from '@hotwired/stimulus';

/*
 * Copia ao portapapeles o valor indicado en data-clipboard-text-value.
 * Uso: <button data-controller="clipboard" data-clipboard-text-value="..." data-action="clipboard#copy">Copiar</button>
 */
export default class extends Controller {
    static values = { text: String };
    static targets = ['button'];

    async copy() {
        await navigator.clipboard.writeText(this.textValue);

        const button = this.hasButtonTarget ? this.buttonTarget : this.element;
        const original = button.textContent;
        button.textContent = 'Copiado!';
        setTimeout(() => {
            button.textContent = original;
        }, 1500);
    }
}
