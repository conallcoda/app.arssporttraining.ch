<div
    class="space-y-5"
    x-data="{
        focusEditorField(model) {
            this.$nextTick(() => {
                setTimeout(() => {
                    const name = String(model);
                    const escaped = window.CSS?.escape ? CSS.escape(name) : name.replaceAll('.', '\\.');
                    const field = this.$el.querySelector(`[name=&quot;${escaped}&quot;]`);

                    field?.focus();
                    field?.select?.();
                }, 75);
            });
        },
    }"
    x-on:athlete-exercise-editor-focus.window="focusEditorField($event.detail.model)"
>
    <div class="min-w-0">
        @unless ($editorOnly)
            <flux:heading size="lg">Edit</flux:heading>
        @endunless
        @if (filled($editingExerciseName))
            <div @class([
                'truncate font-medium',
                'mt-1 text-sm text-zinc-500 dark:text-zinc-400' => ! $editorOnly,
                'text-base text-zinc-800 dark:text-zinc-100' => $editorOnly,
            ])>
                {{ $editingExerciseName }}
            </div>
        @endif
    </div>

    @if ($this->editingExercise)
        <form wire:submit="saveExerciseEdits" class="space-y-5">
            @if (count($this->editSetTabs) > 1)
                <flux:tabs wire:model.live="activeEditSet" variant="segmented" class="w-full overflow-x-auto">
                    @foreach ($this->editSetTabs as $tab)
                        <flux:tab :name="$tab['name']" class="min-w-fit flex-1">{{ $tab['label'] }}</flux:tab>
                    @endforeach
                </flux:tabs>
            @endif

            @foreach ($this->editSetPanels as $panel)
                <div wire:key="edit-set-panel-{{ $panel['id'] }}"
                    @class([
                        'space-y-4' => true,
                        'hidden' => count($this->editSetTabs) > 1 && $activeEditSet !== $panel['tab'],
                    ])>
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            {{ $panel['label'] }}
                        </div>

                        @if ($panel['isSkipped'])
                            <flux:button type="button" size="xs" variant="ghost" wire:click="markEditSetPending({{ $panel['id'] }})">
                                Unskip set
                            </flux:button>
                        @else
                            <flux:button type="button" size="xs" variant="ghost" wire:click="markEditSetSkipped({{ $panel['id'] }})">
                                Skip set
                            </flux:button>
                        @endif
                    </div>

                    @if ($panel['isSkipped'])
                        <div class="rounded-lg border border-sky-300/40 bg-sky-100/70 px-3 py-2 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                            This set was skipped.
                        </div>
                    @else
                        @foreach ($panel['fields'] as $field)
                            <x-form-kit::form.field :field="$field" :prefix="'editValues.'.$panel['id']" />
                        @endforeach
                    @endif
                </div>
            @endforeach

            <div class="flex items-center gap-2 pt-2">
                <flux:button type="submit" variant="primary" class="flex-1">
                    Save
                </flux:button>
                @if ($editorOnly)
                    <flux:button type="button" variant="ghost" wire:click="cancelExerciseEditor">
                        Cancel
                    </flux:button>
                @else
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelExerciseEditor">
                            Cancel
                        </flux:button>
                    </flux:modal.close>
                @endif
            </div>
        </form>
    @endif
</div>
