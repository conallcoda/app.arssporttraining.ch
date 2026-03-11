@use('Coda\Cms\Form\Fields\Select')
@use('Coda\Cms\Form\Fields\Number')
@use('Coda\Cms\Form\Fields\Duration')
@use('Coda\Cms\Form\Fields\Text')
@use('Coda\Cms\Form\Fields\Url')
@use('Coda\Cms\Form\Fields\Textarea')
@use('Coda\Cms\Form\Fields\Date')
@use('Coda\Cms\Form\Fields\RadioSegmented')
@use('Coda\Cms\Form\Fields\Pillbox')
@use('Coda\Cms\Form\Fields\Slider')
@use('Coda\Cms\Form\Fields\Relationship')
@use('Coda\Cms\Form\Fields\Repeater')
@use('Coda\Cms\Form\Fields\Tags')
@use('Coda\Cms\Form\Fields\Tree')
@use('Coda\Cms\Form\Fields\Preset')
@use('Coda\Cms\Form\Fields\Search')
@use('Coda\Cms\Form\Fields\FileUpload')

@props(['field', 'prefix' => null, 'repeaterItems' => null, 'currentIndex' => null])

@php
    $wireModel = $prefix ? "{$prefix}.{$field->name}" : $field->name;
    $resolvedSuffix = method_exists($field, 'resolveSuffix')
        ? $field->resolveSuffix($prefix ? (data_get($this, $prefix) ?? []) : [])
        : $resolvedSuffix ?? null;
@endphp

<div {{ $attributes }}>
    @if ($field instanceof Select)
        @php
            $options = $field->getOptions();

            if ($field->unique && isset($repeaterItems) && isset($currentIndex)) {
                $selectedValues = collect($repeaterItems)
                    ->filter(fn($item, $idx) => $idx !== $currentIndex && !empty($item[$field->name]))
                    ->pluck($field->name)
                    ->map(fn($v) => (string) $v)
                    ->toArray();

                $options = collect($options)
                    ->filter(fn($label, $value) => !in_array((string) $value, $selectedValues, true))
                    ->toArray();
            }

            $selectVariant = $field->variant;
            if ($field->optionView && !$selectVariant) {
                $selectVariant = 'listbox';
            }
            if ($field->multiple && !$selectVariant) {
                $selectVariant = 'listbox';
            }
            if ($field->searchable && !$selectVariant) {
                $selectVariant = 'combobox';
            }
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:select :wire:model.live="$field->live ? $wireModel : null"
                :wire:model.live.blur="!$field->live ? $wireModel : null" placeholder="{{ $field->getPlaceholder() }}"
                data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
                :searchable="$field->searchable" :clearable="$field->clearable" :size="$field->size">
                @if (!$field->multiple && !$field->clearable)
                    <flux:select.option value="">{{ $field->getPlaceholder() }}</flux:select.option>
                @endif
                @foreach ($options as $value => $optionLabel)
                    @if ($field->optionView)
                        <flux:select.option value="{{ $value }}">
                            @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                        </flux:select.option>
                    @else
                        <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Number)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($resolvedSuffix)
                <flux:input.group>
                    @if ($field->disabled)
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                    @elseif ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                            min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @endif
                    <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
                </flux:input.group>
            @else
                @if ($field->disabled)
                    <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                @elseif ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Duration)
        @php
            $siblingData = $prefix ? (data_get($this, $prefix) ?? []) : [];
            $isMasked = $field->isMasked($siblingData);
            $mask = $field->resolveMask($siblingData);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            <flux:input.group>
                @if ($isMasked)
                    <div x-data="masked_input" data-mask="{{ $mask }}" class="flex-1">
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @else
                            <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @endif
                    </div>
                @else
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number"
                            data-field="{{ $field->name }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}" />
                    @endif
                @endif
                <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
            </flux:input.group>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Text)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($resolvedSuffix)
                <flux:input.group>
                    @if ($field->mask)
                        <div x-data="masked_input" data-mask="{{ $field->mask }}" class="flex-1">
                            @if ($field->live)
                                <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                            @else
                                <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                            @endif
                        </div>
                    @else
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                        @else
                            <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                        @endif
                    @endif
                    <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
                </flux:input.group>
            @elseif ($field->mask)
                <div x-data="masked_input" data-mask="{{ $field->mask }}">
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                    @endif
                </div>
            @else
                @if ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Url)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            @if ($field->live)
                <flux:input wire:model.live="{{ $wireModel }}" type="url"
                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
            @else
                <flux:input wire:model.live.blur="{{ $wireModel }}" type="url"
                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Textarea)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            @if ($field->live)
                @if ($field->autosize)
                    <flux:textarea wire:model.live="{{ $wireModel }}" rows="auto"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:textarea wire:model.live="{{ $wireModel }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @else
                @if ($field->autosize)
                    <flux:textarea wire:model.live.blur="{{ $wireModel }}" rows="auto"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:textarea wire:model.live.blur="{{ $wireModel }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Date)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:date-picker wire:model.live="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @else
                <flux:date-picker wire:model.live.blur="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof RadioSegmented)
        @php $currentValue = data_get($this, $wireModel) ?? $field->default; @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @else
                <flux:radio.group wire:model.live.blur="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Pillbox)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live && $field->debounce)
                <div wire:ignore>
                    <flux:pillbox wire:model.live.debounce.300ms="{{ $wireModel }}" multiple :searchable="$field->searchable"
                        :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                        @foreach ($field->getOptions() as $value => $optionLabel)
                            <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                </div>
            @elseif ($field->live)
                <flux:pillbox wire:model.live="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @elseif ($field->blur)
                <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @else
                <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Slider)
        <x-cms::form.slider-with-input :label="$field->getLabel()" :model="$wireModel" :min="$field->min" :max="$field->max"
            :step="$field->step" :suffix="$resolvedSuffix" :ticks="$field->ticks" :required="$field->required" />
    @elseif ($field instanceof Tree)
        @php
            $treeValue = data_get($this, $wireModel);
            $treeValue = is_int($treeValue) ? (int) $treeValue : null;
            $treeOptionsJson = json_encode($field->getTreeOptions());
            $treeKey = md5($treeOptionsJson);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <div x-data="tree_select" data-options="{{ $treeOptionsJson }}"
                data-value="{{ $treeValue }}" data-placeholder="{{ $field->getPlaceholder() }}"
                data-wire-model="{{ $wireModel }}" wire:ignore wire:key="tree-{{ $field->name }}-{{ $treeKey }}">
                <div data-tree-select-container></div>
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Tags)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple searchable
                placeholder="{{ $field->getPlaceholder() }}" data-field="{{ $field->name }}">
                @foreach ($field->getOptions() as $value => $optionLabel)
                    <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                @endforeach
                @if ($field->creatable)
                    <flux:pillbox.option.create
                        x-on:click="$wire.createTag('{{ $field->name }}', $el.closest('[data-flux-options]').querySelector('[data-flux-pillbox-input]').value)"
                        min-length="2"
                    >
                        Create new
                    </flux:pillbox.option.create>
                @endif
            </flux:pillbox>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Relationship)
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                        @endif
                    </div>
                    <flux:button type="button" size="sm" variant="ghost"
                        wire:click="addRelationshipItem('{{ $field->name }}')" icon="plus">
                        Add
                    </flux:button>
                </div>

                @php
                    $items = data_get($this, $wireModel, []);
                    $selectedIds = collect($items)
                        ->pluck($field->valueAttribute)
                        ->filter()
                        ->map(fn($v) => (int) $v)
                        ->toArray();
                @endphp

                @if (is_array($items) && count($items) > 0)
                    @if ($field->sortable)
                        <div class="space-y-2" x-data="sortable_items('{{ $field->name }}')">
                    @else
                        <div class="space-y-2">
                    @endif
                        @foreach ($items as $index => $item)
                            @php
                                $currentValue = $item[$field->valueAttribute] ?? null;
                                $filteredOptions = collect($field->getOptions())
                                    ->filter(
                                        fn($label, $value) => $value == $currentValue ||
                                            !in_array((int) $value, $selectedIds, true),
                                    )
                                    ->toArray();
                            @endphp
                            @if ($field->sortable)
                                <div class="flex items-center gap-2"
                                    wire:key="{{ $field->name }}-{{ $index }}"
                                    data-item-index="{{ $index }}"
                                    @dragover="handleDragOver($event, {{ $index }})"
                                    @dragleave="handleDragLeave($event)"
                                    @drop="handleDrop($event, {{ $index }})">
                                    <div draggable="true"
                                        @dragstart="handleDragStart($event, {{ $index }})"
                                        @dragend="handleDragEnd($event)"
                                        class="shrink-0 cursor-grab active:cursor-grabbing text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                        <flux:icon.grip class="size-4" />
                                    </div>
                            @else
                                <div class="flex items-center gap-2"
                                    wire:key="{{ $field->name }}-{{ $index }}">
                            @endif
                                <div class="flex-1 min-w-0">
                                    <flux:select
                                        wire:model.live.blur="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                        placeholder="{{ $field->getPlaceholder() }}" size="sm"
                                        variant="listbox" searchable clearable
                                        data-field="{{ $field->name }}" data-index="{{ $index }}">
                                        @foreach ($filteredOptions as $value => $optionLabel)
                                            <flux:select.option value="{{ $value }}">{{ $optionLabel }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <div class="flex gap-0.5">
                                    <flux:button type="button" size="xs" variant="ghost"
                                        wire:click="removeRelationshipItem('{{ $field->name }}', {{ $index }})">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500">No items added yet.</p>
                @endif
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Repeater)
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                        @endif
                    </div>
                    <flux:button type="button" size="sm" variant="ghost"
                        wire:click="addRepeaterItem('{{ $field->name }}')" icon="plus">
                        Add
                    </flux:button>
                </div>

                @php
                    $items = data_get($this, $wireModel, []);
                @endphp

                @if (is_array($items) && count($items) > 0)
                    <div class="space-y-3">
                        @foreach ($items as $index => $item)
                            <div class="flex items-start gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg"
                                wire:key="{{ $field->name }}-{{ $index }}">
                                <div class="flex-1 space-y-3">
                                    @foreach ($field->schema as $childField)
                                        <x-cms::form.field :field="$childField" :prefix="$wireModel . '.' . $index" :repeater-items="$items"
                                            :current-index="$index" />
                                    @endforeach
                                </div>
                                <flux:button type="button" size="xs" variant="ghost" icon="trash"
                                    wire:click="removeRepeaterItem('{{ $field->name }}', {{ $index }})" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500">No items added yet.</p>
                @endif
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Preset)
        @php
            $isMapped = $field->isMapped();
            $currentValue = $isMapped ? null : data_get($this, $wireModel);
            $anyPresetActive = $isMapped
                ? collect($field->presets)->contains(fn($p) => collect($p['values'])->every(fn($v, $k) => data_get($this, "{$prefix}.{$k}") === $v))
                : collect($field->presets)->contains(fn($p) => $p['value'] === $currentValue);
        @endphp
        <div x-data="{ showOther: {{ !$anyPresetActive && $field->hasOther() ? 'true' : 'false' }} }">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($field->presets as $preset)
                    @if ($isMapped)
                        @php
                            $setCommands = collect($preset['values'])
                                ->map(fn($val, $key) => "\$wire.set('{$prefix}.{$key}', '{$val}')")
                                ->implode('; ');
                            $isActive = collect($preset['values'])
                                ->every(fn($val, $key) => data_get($this, "{$prefix}.{$key}") === $val);
                        @endphp
                        @if ($isActive)
                            <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                                <flux:button
                                    x-on:click="{{ $setCommands }}; showOther = false"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $preset['label'] }}
                                </flux:button>
                            </span>
                        @else
                            <flux:button
                                x-on:click="{{ $setCommands }}; showOther = false"
                                variant="ghost"
                                size="sm"
                            >
                                {{ $preset['label'] }}
                            </flux:button>
                        @endif
                    @else
                        @if ($preset['value'] === $currentValue)
                            <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                                <flux:button
                                    wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                                    x-on:click="showOther = false"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $preset['label'] }}
                                </flux:button>
                            </span>
                        @else
                            <flux:button
                                wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                                x-on:click="showOther = false"
                                variant="ghost"
                                size="sm"
                            >
                                {{ $preset['label'] }}
                            </flux:button>
                        @endif
                    @endif
                @endforeach
                @if ($field->hasOther())
                    <span class="inline-flex rounded-lg" x-bind:class="showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                        <flux:button
                            x-on:click="showOther = !showOther"
                            variant="ghost"
                            size="sm"
                        >
                            Other
                        </flux:button>
                    </span>
                @endif
            </div>
            @if ($field->otherField)
                <div x-show="showOther" x-cloak class="mt-3">
                    <x-cms::form.field :field="$field->otherField" :prefix="$prefix" />
                </div>
            @elseif (!empty($field->otherFields))
                <div x-show="showOther" x-cloak class="mt-3 flex items-end gap-3">
                    @foreach ($field->otherFields as $otherField)
                        <x-cms::form.field :field="$otherField" :prefix="$prefix" class="flex-1" />
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($field instanceof Search)
        <flux:input wire:model.live.debounce.300ms="{{ $wireModel }}" placeholder="{{ $field->getPlaceholder() }}" size="{{ $field->size }}" clearable>
            <x-slot:icon>
                <flux:icon.search variant="micro" />
            </x-slot:icon>
        </flux:input>
    @elseif ($field instanceof FileUpload)
        @php
            $mediaModel = "mediaUploads.{$field->name}";
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->multiple)
                <flux:file-upload wire:model="{{ $mediaModel }}" multiple>
                    <flux:file-upload.dropzone
                        heading="{{ $field->dropzoneHeading }}"
                        text="{{ $field->dropzoneText }}"
                        inline
                    />
                </flux:file-upload>
            @else
                <flux:file-upload wire:model="{{ $mediaModel }}">
                    <flux:file-upload.dropzone
                        heading="{{ $field->dropzoneHeading }}"
                        text="{{ $field->dropzoneText }}"
                        inline
                    />
                </flux:file-upload>
            @endif

            @php
                $existingItems = $this->existingMedia[$field->name] ?? [];
                $newUploads = $this->mediaUploads[$field->name] ?? ($field->multiple ? [] : null);
            @endphp

            <div class="mt-3 flex flex-col gap-2" x-data="{ previewUrl: null }">
                @foreach ($existingItems as $media)
                    <div class="cursor-pointer" @click="previewUrl = '{{ $media['url'] }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                        <flux:file-item
                            wire:key="existing-{{ $field->name }}-{{ $media['id'] }}"
                            :heading="$media['name']"
                            :image="$media['url']"
                            :size="$media['size']"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove @click.stop wire:click="removeExistingMedia('{{ $field->name }}', {{ $media['id'] }})" />
                            </x-slot>
                        </flux:file-item>
                    </div>
                @endforeach

                @if (is_array($newUploads))
                    @foreach ($newUploads as $index => $upload)
                        @php
                            $uploadExists = $upload->exists();
                            $uploadName = $upload->getClientOriginalName();
                            $uploadSize = $uploadExists ? $upload->getSize() : null;
                            $uploadImage = ($uploadExists && $upload->isPreviewable()) ? $upload->temporaryUrl() : null;
                        @endphp
                        @if ($uploadImage)
                            <div class="cursor-pointer" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                                <flux:file-item
                                    wire:key="upload-{{ $field->name }}-{{ $index }}"
                                    :heading="$uploadName"
                                    :image="$uploadImage"
                                    :size="$uploadSize"
                                >
                                    <x-slot name="actions">
                                        <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" />
                                    </x-slot>
                                </flux:file-item>
                            </div>
                        @else
                            <flux:file-item
                                wire:key="upload-{{ $field->name }}-{{ $index }}"
                                :heading="$uploadName"
                                :size="$uploadSize"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" />
                                </x-slot>
                            </flux:file-item>
                        @endif
                    @endforeach
                @elseif ($newUploads)
                    @php
                        $uploadExists = $newUploads->exists();
                        $uploadName = $newUploads->getClientOriginalName();
                        $uploadSize = $uploadExists ? $newUploads->getSize() : null;
                        $uploadImage = ($uploadExists && $newUploads->isPreviewable()) ? $newUploads->temporaryUrl() : null;
                    @endphp
                    @if ($uploadImage)
                        <div class="cursor-pointer" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                            <flux:file-item
                                wire:key="upload-{{ $field->name }}-single"
                                :heading="$uploadName"
                                :image="$uploadImage"
                                :size="$uploadSize"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', 0)" />
                                </x-slot>
                            </flux:file-item>
                        </div>
                    @else
                        <flux:file-item
                            wire:key="upload-{{ $field->name }}-single"
                            :heading="$uploadName"
                            :size="$uploadSize"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', 0)" />
                            </x-slot>
                        </flux:file-item>
                    @endif
                @endif

                <flux:modal :name="'image-preview-' . $field->name" variant="bare" class="w-auto max-w-[90vw] bg-transparent! shadow-none!">
                    <div class="flex items-center justify-center" @click="$flux.modal('image-preview-{{ $field->name }}').close()">
                        <img :src="previewUrl" @click.stop class="max-h-[85vh] max-w-[85vw] rounded-lg object-contain" />
                    </div>
                </flux:modal>
            </div>
            <flux:error name="{{ $mediaModel }}" />
        </flux:field>
    @endif
</div>
