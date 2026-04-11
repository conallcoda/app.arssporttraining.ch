<div>
    <flux:modal :name="$name" :flyout="$flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:focus-field.window="
            const content = $el.querySelector('.space-y-6');
            content.style.visibility = 'hidden';
            setTimeout(() => {
                focusModalField($el, $event.detail.field, $event.detail.index);
                content.style.visibility = 'visible';
            }, 150)
        "
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>
            @if ($openCount > 0)
                <form wire:submit="submit" class="space-y-4">
                    @if ($groupMode && !empty($availableAthletes))
                        <flux:field>
                            <flux:label>{{ __('Athlete') }}</flux:label>
                            <flux:select wire:model.live="data.user_id" variant="listbox" placeholder="{{ __('Select athlete...') }}">
                                @foreach ($availableAthletes as $athlete)
                                    <flux:select.option :value="$athlete['id']">{{ $athlete['name'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('data.user_id')
                                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                            @enderror
                        </flux:field>
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
                    <div class="flex items-center gap-2 pt-4">
                        <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                        </flux:modal.close>
                        @if ($showDelete && !empty($data['id']))
                            <flux:spacer />
                            <flux:button variant="ghost" icon="trash-2" wire:click="requestDelete" />
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
