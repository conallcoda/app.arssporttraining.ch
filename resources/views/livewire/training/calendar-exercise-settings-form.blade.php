<div class="focus:outline-none">
    <flux:modal :name="$name" :flyout="$flyout" class="max-w-fit"
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

            <div class="grid grid-cols-[minmax(420px,2fr)_3fr] gap-8">
                <div wire:key="form-{{ $this->contextExerciseId }}-{{ $this->contextExerciseProgramId }}-{{ md5(json_encode($data['config'] ?? [])) }}">
                    <form wire:submit="submit" class="space-y-4">
                        @foreach ($this->fieldsets as $item)
                            @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                                <x-form-kit::form.fieldset-tabs :group="$item" />
                            @else
                                <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                            @endif
                        @endforeach
                        <div class="flex gap-2 pt-4">
                            <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                            <flux:modal.close>
                                <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                            </flux:modal.close>
                        </div>
                    </form>
                </div>

                @if ($this->contextExerciseId)
                    <div class="min-w-0" wire:key="grid-{{ $this->contextExerciseId }}-{{ $this->contextExerciseProgramId }}-{{ md5(json_encode($data['config'] ?? [])) }}"
                        x-on:grid-setting-click="focusModalField($el.closest('[data-flux-modal]'), $event.detail.field)">
                        @if ($scheduled)
                            <x-training.exercise-grid
                                :grid="$this->previewGrid"
                                :name="$activeTitle ?? $title"
                                :showHeader="false"
                                :showMenu="false"
                                :settingClickable="true"
                                :weekLabels="$weekLabels"
                                :weekSessions="$weekSessions"
                                :collapseWeeks="false"
                            />
                        @else
                            <div class="flex items-center justify-center rounded-lg border border-zinc-200 p-8 dark:border-zinc-700">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Exercise not scheduled yet') }}</flux:text>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
