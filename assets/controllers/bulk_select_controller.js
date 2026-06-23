import { Controller } from '@hotwired/stimulus';

/*
 * Selección múltiple de filas dunha táboa + barra de accións en lote.
 * Uso:
 *   <div data-controller="bulk-select">
 *     <input type="checkbox" data-bulk-select-target="selectAll" data-action="bulk-select#toggleAll">
 *     <input type="checkbox" data-bulk-select-target="checkbox" value="{{ id }}" data-action="bulk-select#refresh"> (por fila)
 *     <div data-bulk-select-target="bar" class="hidden">
 *       <span data-bulk-select-target="count"></span>
 *       <form data-action="submit->bulk-select#submitWithIds" data-confirm="opcional, texto de confirm()">...</form>
 *     </div>
 *   </div>
 *
 * Ao enviar un form dentro da barra, inxéctanse os ids seleccionados como
 * <input type="hidden" name="ids[]"> antes de deixar que o submit continúe.
 */
export default class extends Controller {
    static targets = ['selectAll', 'checkbox', 'bar', 'count'];

    connect() {
        this.refresh();
    }

    toggleAll() {
        this.checkboxTargets.forEach((checkbox) => {
            checkbox.checked = this.selectAllTarget.checked;
        });
        this.refresh();
    }

    refresh() {
        const selected = this.selectedIds();

        this.countTarget.textContent = selected.length;
        this.barTarget.classList.toggle('hidden', selected.length === 0);
        this.selectAllTarget.checked = this.checkboxTargets.length > 0 && selected.length === this.checkboxTargets.length;
    }

    submitWithIds(event) {
        const ids = this.selectedIds();
        if (ids.length === 0) {
            event.preventDefault();
            return;
        }

        const form = event.target;
        const confirmMessage = form.dataset.confirm;
        if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();
            return;
        }

        form.querySelectorAll('input[name="ids[]"]').forEach((input) => input.remove());
        for (const id of ids) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        }
    }

    selectedIds() {
        return this.checkboxTargets.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
    }
}
