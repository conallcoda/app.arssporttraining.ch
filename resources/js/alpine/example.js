document.addEventListener('alpine:init', () => {
    Alpine.data('example_of_data_component_name_always_snake_case', () => ({
        config: {},
        init() {
            this.config = JSON.parse(atob(this.$el.getAttribute('data-config')));
        },
        destroy() {
        }
    }));
});
