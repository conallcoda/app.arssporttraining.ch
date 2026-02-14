<div>
    <flux:modal :name="$name" :flyout="$flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
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
            const panel = input.closest('[data-flux-tab-panel]');
            if (panel) {
                const panelName = panel.getAttribute('name');
                const tabGroup = panel.closest('[data-flux-tab-group]');
                if (tabGroup && panelName) {
                    const tab = tabGroup.querySelector(`[data-flux-tab][name='${panelName}']`);
                    if (tab) tab.click();
                }
            }
            $nextTick(() => {
                const trigger = input.querySelector('[data-flux-pillbox-trigger]');
                if (trigger) {
                    trigger.click();
                } else {
                    input.focus();
                }
            });
        })">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>
            @if ($openCount > 0)
                <form wire:submit="submit" class="space-y-4">
                    @foreach ($this->fieldsets as $item)
                        @if ($item instanceof \Coda\Cms\Form\FormFieldsetGroup)
                            <x-cms::form.fieldset-tabs :group="$item" />
                        @else
                            <x-cms::form.fieldset
                                :fieldset="$item"
                                :prefix="$item->prefix ?? 'data'"
                                :showLegend="count($this->fieldsets) > 1"
                            />
                        @endif
                    @endforeach
                    <div class="flex gap-2 pt-4">
                        <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                        </flux:modal.close>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
