<div class="focus:outline-none">
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

            <div class="grid grid-cols-[minmax(420px,2fr)_3fr] gap-8">
                <div class="space-y-4">
                    <form wire:submit="submit" class="space-y-4" wire:key="form-{{ $openCount }}">
                        @foreach ($this->fieldsets as $item)
                            @if ($item instanceof \Coda\Cms\Form\FormFieldsetGroup)
                                <x-cms::form.fieldset-tabs :group="$item" />
                            @else
                                <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
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

                <div class="space-y-4 min-w-0">
                    @if ($showPreview && $showData)
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" wire:click="$set('activeTab', 'preview')"
                                :variant="$activeTab === 'preview' ? 'primary' : 'ghost'">
                                Preview
                            </flux:button>
                            <flux:button size="sm" wire:click="$set('activeTab', 'data')"
                                :variant="$activeTab === 'data' ? 'primary' : 'ghost'">
                                Data
                            </flux:button>
                        </div>
                    @endif

                    @if ($showData && ($activeTab === 'data' || !$showPreview))
                        <pre class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
                    @elseif ($showPreview)
                        @php
                            $grid = $this->previewGrid;
                        @endphp

                        <div wire:key="preview-grid-{{ md5(json_encode($data['config'])) }}">
                            <x-training.exercise-grid
                                :grid="$grid"
                                :name="$data['name'] ?? 'Untitled'"
                                :summary="$grid->summary"
                                :showMenu="false"
                                :editable="true"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>
</div>
