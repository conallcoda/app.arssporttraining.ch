document.addEventListener('alpine:init', () => {
    Alpine.data('form_modal', (modalName) => ({
        modalName: modalName,

        open(data = {}, title = null, focusField = null, focusIndex = null) {
            Livewire.dispatch('open-' + this.modalName, { data, title, focusField, focusIndex });
        },

        close() {
            $flux.modal(this.modalName).close();
        }
    }));
});
