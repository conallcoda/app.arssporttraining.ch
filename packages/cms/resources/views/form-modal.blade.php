<div>
    @foreach ($this->fieldCreateModals() as $modal)
        <livewire:cms.form-modal
            :name="$modal['name']"
            :title="$modal['title']"
            :form-data-class="$modal['formDataClass']"
            :submit-label="$modal['submitLabel']"
            :max-width="'max-w-4xl'"
            :context-data="$modal['contextData'] ?? []"
            :exclude-fields="$modal['excludeFields'] ?? []"
            :persist-on-submit="true"
            :key="$modal['name']"
        />
    @endforeach

    <x-cms::modal :name="$name" :flyout="$flyout" :max-width="$maxWidth"
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
            @if ($openCount > 0)
                <form id="modal-form-{{ $name }}" wire:submit="submit" class="space-y-4">
                    @if (count($formTypes) > 1)
                        <flux:radio.group wire:model.live="activeFormType" variant="segmented" class="w-full">
                            @foreach ($formTypes as $type)
                                <flux:radio :value="$type['key']" :label="$type['label']" :icon="$type['icon'] ?? null" />
                            @endforeach
                        </flux:radio.group>
                    @endif

                    @foreach ($this->fieldsets as $item)
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" />
                        @else
                            <x-form-kit::form.fieldset
                                :fieldset="$item"
                                :prefix="$item->prefix ?? 'data'"
                                :showLegend="count($this->fieldsets) > 1"
                            />
                        @endif
                    @endforeach
                </form>
            @endif
        </x-cms::modal.body>
        <x-cms::modal.footer>
            <div class="flex items-center gap-2">
                <flux:button type="submit" form="modal-form-{{ $name }}" variant="primary" class="flex-1">{{ $this->activeFormTypeConfig()['submitLabel'] ?? $submitLabel }}</flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                </flux:modal.close>
                @if ($showDelete && !empty($data['id']))
                    <flux:spacer />
                    <flux:button variant="ghost" icon="trash-2" wire:click="requestDelete" />
                @endif
            </div>
        </x-cms::modal.footer>
    </x-cms::modal>
</div>
