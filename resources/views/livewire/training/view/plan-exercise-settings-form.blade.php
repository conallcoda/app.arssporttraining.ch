<div class="focus:outline-none">
    <x-cms::modal :name="$name" :flyout="$flyout" :max-width="$maxWidth" padding="p-8"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:focus-field.window="
            const content = $el.querySelector('[data-modal-focus-content]');
            content.style.visibility = 'hidden';
            setTimeout(() => {
                focusModalField($el, $event.detail.field, $event.detail.index);
                content.style.visibility = 'visible';
            }, 150)
        "
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <x-cms::modal.header :title="$activeTitle ?? $title" />
        <x-cms::modal.body class="space-y-6">

            <form id="modal-form-{{ $name }}" wire:submit="submit" class="space-y-4">
                @foreach ($this->fieldsets as $item)
                    @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                        <x-form-kit::form.fieldset-tabs :group="$item" />
                    @else
                        <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                    @endif
                @endforeach
            </form>
        </x-cms::modal.body>
        <x-cms::modal.footer>
            <div class="flex gap-2">
                <flux:button type="submit" form="modal-form-{{ $name }}" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                </flux:modal.close>
            </div>
        </x-cms::modal.footer>
    </x-cms::modal>
</div>
