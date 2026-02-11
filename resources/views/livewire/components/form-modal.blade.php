<div>
    <flux:modal :name="$name" :flyout="$flyout" :class="$flyout ? $width : ''"
        x-on:focus-field.window="$nextTick(() => {
            const field = $event.detail.field;
            const index = $event.detail.index;
            let input;
            if (index !== null && index !== undefined) {
                input = $el.querySelector(`[data-field='${field}'][data-index='${index}']`);
            } else {
                input = $el.querySelector(`[data-field='${field}']`);
            }
            if (!input) return;
            const trigger = input.querySelector('[data-flux-pillbox-trigger]');
            if (trigger) {
                trigger.click();
            } else {
                input.focus();
            }
        })">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>
            <form wire:submit="submit" class="space-y-4">
                @foreach ($this->fieldsets as $fieldset)
                    <x-cms.form.fieldset
                        :fieldset="$fieldset"
                        :prefix="$fieldset->prefix ?? 'data'"
                        :showLegend="count($this->fieldsets) > 1"
                    />
                @endforeach
                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
