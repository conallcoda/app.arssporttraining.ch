@php
    $layoutContainer = $this->detailsLayout?->container ?? 'card';
    $rootTabs = $this->detailsRootTabs;
    $showLegend = count($this->detailsFieldsetsByName) > 1;
    $stickyBackgroundClass = 'bg-white dark:bg-zinc-800';
    $stickyInsetClass = match ($layoutContainer) {
        'section' => '-mx-6 px-6',
        default => '',
    };
    $stickyWrapperClass = "sticky z-10 {$stickyBackgroundClass} {$stickyInsetClass}";

    if ($rootTabs) {
        $defaultRootTab = $rootTabs->tabs[0]->name ?? null;

        if ($defaultRootTab && method_exists($this, 'initializeTabState')) {
            $this->initializeTabState($defaultRootTab);
        }
    }
@endphp

<div class="space-y-6">
    @if ($layoutContainer === 'none')
        <form wire:submit="save" class="space-y-6">
            @if ($rootTabs)
                <flux:tab.group>
                    <div class="{{ $stickyWrapperClass }} top-0">
                        <div class="space-y-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                                    <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                                </div>

                                <div class="flex items-center gap-2">
                                    <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                        Back
                                    </flux:button>
                                    <flux:button type="submit" variant="primary">Save</flux:button>
                                </div>
                            </div>

                            <flux:tabs wire:model.live="activeTab" :scrollable="$rootTabs->scrollable">
                                @foreach ($rootTabs->tabs as $tab)
                                    <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                                @endforeach
                            </flux:tabs>
                        </div>
                    </div>

                    @foreach ($rootTabs->tabs as $tab)
                        <flux:tab.panel :name="$tab->name" class="pt-6">
                            <div class="space-y-6">
                                @foreach ($tab->schema as $child)
                                    @include('cms::details-layout.node', [
                                        'node' => $child,
                                        'fieldsetsByName' => $this->detailsFieldsetsByName,
                                        'showLegend' => $showLegend,
                                        'statePath' => null,
                                    ])
                                @endforeach
                            </div>
                        </flux:tab.panel>
                    @endforeach
                </flux:tab.group>
            @else
                <div class="{{ $stickyWrapperClass }} top-0">
                    <div class="flex items-start justify-between gap-4 py-4">
                        <div class="space-y-1">
                            <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                            <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                Back
                            </flux:button>
                            <flux:button type="submit" variant="primary">Save</flux:button>
                        </div>
                    </div>
                </div>

                @if ($this->detailsLayout)
                    @foreach ($this->detailsLayout->schema as $node)
                        @include('cms::details-layout.node', [
                            'node' => $node,
                            'fieldsetsByName' => $this->detailsFieldsetsByName,
                            'showLegend' => $showLegend,
                            'statePath' => 'activeTab',
                        ])
                    @endforeach
                @else
                    @foreach ($this->fieldsets as $item)
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" statePath="activeTab" />
                        @else
                            <x-form-kit::form.fieldset
                                :fieldset="$item"
                                :prefix="$item->prefix ?? 'data'"
                                :showLegend="$showLegend"
                            />
                        @endif
                    @endforeach
                @endif
            @endif
        </form>
    @elseif ($layoutContainer === 'section')
        <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <form wire:submit="save" class="space-y-6">
                @if ($rootTabs)
                    <flux:tab.group>
                        <div class="{{ $stickyWrapperClass }} top-0">
                            <div class="space-y-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                                        <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                            Back
                                        </flux:button>
                                        <flux:button type="submit" variant="primary">Save</flux:button>
                                    </div>
                                </div>

                                <flux:tabs wire:model.live="activeTab" :scrollable="$rootTabs->scrollable">
                                    @foreach ($rootTabs->tabs as $tab)
                                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                                    @endforeach
                                </flux:tabs>
                            </div>
                        </div>

                        @foreach ($rootTabs->tabs as $tab)
                            <flux:tab.panel :name="$tab->name" class="pt-6">
                                <div class="space-y-6">
                                    @foreach ($tab->schema as $child)
                                        @include('cms::details-layout.node', [
                                            'node' => $child,
                                            'fieldsetsByName' => $this->detailsFieldsetsByName,
                                            'showLegend' => $showLegend,
                                            'statePath' => null,
                                        ])
                                    @endforeach
                                </div>
                            </flux:tab.panel>
                        @endforeach
                    </flux:tab.group>
                @else
                    <div class="{{ $stickyWrapperClass }} top-0">
                        <div class="flex items-start justify-between gap-4 py-4">
                            <div class="space-y-1">
                                <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                                <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                    Back
                                </flux:button>
                                <flux:button type="submit" variant="primary">Save</flux:button>
                            </div>
                        </div>
                    </div>

                    @if ($this->detailsLayout)
                        @foreach ($this->detailsLayout->schema as $node)
                            @include('cms::details-layout.node', [
                                'node' => $node,
                                'fieldsetsByName' => $this->detailsFieldsetsByName,
                                'showLegend' => $showLegend,
                                'statePath' => 'activeTab',
                            ])
                        @endforeach
                    @else
                        @foreach ($this->fieldsets as $item)
                            @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                                <x-form-kit::form.fieldset-tabs :group="$item" statePath="activeTab" />
                            @else
                                <x-form-kit::form.fieldset
                                    :fieldset="$item"
                                    :prefix="$item->prefix ?? 'data'"
                                    :showLegend="$showLegend"
                                />
                            @endif
                        @endforeach
                    @endif
                @endif
            </form>
        </section>
    @else
        <flux:card>
            <form wire:submit="save" class="space-y-6">
                @if ($rootTabs)
                    <flux:tab.group>
                        <div class="{{ $stickyWrapperClass }} top-0">
                            <div class="space-y-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                                        <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                            Back
                                        </flux:button>
                                        <flux:button type="submit" variant="primary">Save</flux:button>
                                    </div>
                                </div>

                                <flux:tabs wire:model.live="activeTab" :scrollable="$rootTabs->scrollable">
                                    @foreach ($rootTabs->tabs as $tab)
                                        <flux:tab :name="$tab->name">{{ $tab->label }}</flux:tab>
                                    @endforeach
                                </flux:tabs>
                            </div>
                        </div>

                        @foreach ($rootTabs->tabs as $tab)
                            <flux:tab.panel :name="$tab->name" class="pt-6">
                                <div class="space-y-6">
                                    @foreach ($tab->schema as $child)
                                        @include('cms::details-layout.node', [
                                            'node' => $child,
                                            'fieldsetsByName' => $this->detailsFieldsetsByName,
                                            'showLegend' => $showLegend,
                                            'statePath' => null,
                                        ])
                                    @endforeach
                                </div>
                            </flux:tab.panel>
                        @endforeach
                    </flux:tab.group>
                @else
                    <div class="{{ $stickyWrapperClass }} top-0">
                        <div class="flex items-start justify-between gap-4 py-4">
                            <div class="space-y-1">
                                <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                                <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                                    Back
                                </flux:button>
                                <flux:button type="submit" variant="primary">Save</flux:button>
                            </div>
                        </div>
                    </div>

                    @if ($this->detailsLayout)
                        @foreach ($this->detailsLayout->schema as $node)
                            @include('cms::details-layout.node', [
                                'node' => $node,
                                'fieldsetsByName' => $this->detailsFieldsetsByName,
                                'showLegend' => $showLegend,
                                'statePath' => 'activeTab',
                            ])
                        @endforeach
                    @else
                        @foreach ($this->fieldsets as $item)
                            @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                                <x-form-kit::form.fieldset-tabs :group="$item" statePath="activeTab" />
                            @else
                                <x-form-kit::form.fieldset
                                    :fieldset="$item"
                                    :prefix="$item->prefix ?? 'data'"
                                    :showLegend="$showLegend"
                                />
                            @endif
                        @endforeach
                    @endif
                @endif
            </form>
        </flux:card>
    @endif
</div>
