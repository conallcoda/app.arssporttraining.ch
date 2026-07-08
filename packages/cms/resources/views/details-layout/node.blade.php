@php
    $fieldsetsByName = $fieldsetsByName ?? [];
    $showLegend = $showLegend ?? false;
    $statePath = $statePath ?? null;
    $mode = $mode ?? 'full';
@endphp

@if ($node instanceof \Coda\Cms\Layout\Tabs)
    @php
        $defaultTab = $node->tabs[0]->name ?? null;

        if ($statePath && $defaultTab && method_exists($this, 'initializeTabState')) {
            $this->initializeTabState($defaultTab);
        }
    @endphp

    <div class="space-y-4">
        @if ($node->label)
            <flux:heading size="sm">{{ $node->label }}</flux:heading>
        @endif

        @if ($mode === 'tabs')
            @if ($statePath)
                <flux:tabs wire:model.live="{{ $statePath }}" :scrollable="$node->scrollable">
                    @foreach ($node->tabs as $tab)
                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                    @endforeach
                </flux:tabs>
            @else
                <flux:tabs :scrollable="$node->scrollable">
                    @foreach ($node->tabs as $tab)
                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                    @endforeach
                </flux:tabs>
            @endif
        @elseif ($mode === 'panels')
            @foreach ($node->tabs as $tab)
                <flux:tab.panel :name="$tab->name" class="pt-6">
                    <div class="space-y-6">
                        @foreach ($tab->schema as $child)
                            @include('cms::details-layout.node', [
                                'node' => $child,
                                'fieldsetsByName' => $fieldsetsByName,
                                'showLegend' => $showLegend,
                                'statePath' => null,
                            ])
                        @endforeach
                    </div>
                </flux:tab.panel>
            @endforeach
        @elseif ($statePath)
            <flux:tab.group>
                <flux:tabs wire:model.live="{{ $statePath }}" :scrollable="$node->scrollable">
                    @foreach ($node->tabs as $tab)
                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($node->tabs as $tab)
                    <flux:tab.panel :name="$tab->name" class="pt-6">
                        <div class="space-y-6">
                            @foreach ($tab->schema as $child)
                                @include('cms::details-layout.node', [
                                    'node' => $child,
                                    'fieldsetsByName' => $fieldsetsByName,
                                    'showLegend' => $showLegend,
                                    'statePath' => null,
                                ])
                            @endforeach
                        </div>
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @else
            <flux:tab.group>
                <flux:tabs :scrollable="$node->scrollable">
                    @foreach ($node->tabs as $tab)
                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($node->tabs as $tab)
                    <flux:tab.panel :name="$tab->name" class="pt-6">
                        <div class="space-y-6">
                            @foreach ($tab->schema as $child)
                                @include('cms::details-layout.node', [
                                    'node' => $child,
                                    'fieldsetsByName' => $fieldsetsByName,
                                    'showLegend' => $showLegend,
                                    'statePath' => null,
                                ])
                            @endforeach
                        </div>
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @endif
    </div>
@elseif ($node instanceof \Coda\Cms\Layout\Grid)
    @php
        $gridColumnsClass = [
            1 => 'md:grid-cols-1',
            2 => 'md:grid-cols-2',
            3 => 'md:grid-cols-3',
            4 => 'md:grid-cols-4',
            5 => 'md:grid-cols-5',
            6 => 'md:grid-cols-6',
            7 => 'md:grid-cols-7',
            8 => 'md:grid-cols-8',
            9 => 'md:grid-cols-9',
            10 => 'md:grid-cols-10',
            11 => 'md:grid-cols-11',
            12 => 'md:grid-cols-12',
        ][$node->columns] ?? 'md:grid-cols-12';
    @endphp

    <div class="grid grid-cols-1 gap-6 {{ $gridColumnsClass }}">
        @foreach ($node->schema as $child)
            @include('cms::details-layout.node', [
                'node' => $child,
                'fieldsetsByName' => $fieldsetsByName,
                'showLegend' => $showLegend,
                'statePath' => $statePath,
            ])
        @endforeach
    </div>
@elseif ($node instanceof \Coda\Cms\Layout\Column)
    @php
        $columnSpanClass = [
            1 => 'md:col-span-1',
            2 => 'md:col-span-2',
            3 => 'md:col-span-3',
            4 => 'md:col-span-4',
            5 => 'md:col-span-5',
            6 => 'md:col-span-6',
            7 => 'md:col-span-7',
            8 => 'md:col-span-8',
            9 => 'md:col-span-9',
            10 => 'md:col-span-10',
            11 => 'md:col-span-11',
            12 => 'md:col-span-12',
        ][$node->span] ?? 'md:col-span-12';
    @endphp

    <div class="min-w-0 {{ $columnSpanClass }}">
        <div class="space-y-6">
            @foreach ($node->schema as $child)
                @include('cms::details-layout.node', [
                    'node' => $child,
                    'fieldsetsByName' => $fieldsetsByName,
                    'showLegend' => $showLegend,
                    'statePath' => $statePath,
                ])
            @endforeach
        </div>
    </div>
@elseif ($node instanceof \Coda\Cms\Layout\Section)
    <section class="space-y-4">
        @if ($node->label)
            <flux:heading size="sm">{{ $node->label }}</flux:heading>
        @endif

        @foreach ($node->schema as $child)
            @include('cms::details-layout.node', [
                'node' => $child,
                'fieldsetsByName' => $fieldsetsByName,
                'showLegend' => $showLegend,
                'statePath' => $statePath,
            ])
        @endforeach
    </section>
@elseif ($node instanceof \Coda\Cms\Layout\Fieldset)
    @php($fieldset = $fieldsetsByName[$node->name] ?? null)

    @if ($fieldset)
        <x-form-kit::form.fieldset
            :fieldset="$fieldset"
            :prefix="$fieldset->prefix ?? 'data'"
            :showLegend="$showLegend"
        />
    @endif
@endif
