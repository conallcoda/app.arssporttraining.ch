@php
    $hasChildren = isset($node['children']) && count($node['children']) > 0;
    $isExpanded = in_array($node['id'], $expandedNodes);
    $canHaveChildren = in_array($nodeType, ['season', 'block', 'week', 'session']);

    $typeConfig = [
        'season' => ['label' => 'Season', 'icon' => 'lucide-calendar', 'color' => 'primary', 'childType' => 'block'],
        'block' => ['label' => 'Block', 'icon' => 'lucide-box', 'color' => 'success', 'childType' => 'week'],
        'week' => ['label' => 'Week', 'icon' => 'lucide-calendar-days', 'color' => 'warning', 'childType' => 'session'],
        'session' => ['label' => 'Session', 'icon' => 'lucide-dumbbell', 'color' => 'info', 'childType' => 'exercise'],
        'exercise' => ['label' => 'Exercise', 'icon' => 'lucide-activity', 'color' => 'gray', 'childType' => null],
    ];

    $config = $typeConfig[$nodeType] ?? $typeConfig['season'];
    $indent = $level * 24;
@endphp

<div class="training-node" style="margin-left: {{ $indent }}px;">
    <div class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
        @if($hasChildren || $canHaveChildren)
            <button
                wire:click="toggleNode({{ $node['id'] }})"
                class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
                @if($hasChildren)
                    @if($isExpanded)
                        <x-filament::icon icon="lucide-chevron-down" class="w-4 h-4" />
                    @else
                        <x-filament::icon icon="lucide-chevron-right" class="w-4 h-4" />
                    @endif
                @else
                    <span class="w-4 h-4"></span>
                @endif
            </button>
        @else
            <span class="w-6"></span>
        @endif

        <x-filament::icon
            :icon="$config['icon']"
            class="w-4 h-4 text-{{ $config['color'] }}-500"
        />

        <div class="flex-1">
            @if($editingNode === $node['id'])
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model="editingName"
                        wire:keydown.enter="saveEdit({{ $node['id'] }}, '{{ $nodeType }}')"
                        wire:keydown.escape="cancelEdit"
                        class="flex-1 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 focus:ring-2 focus:ring-primary-500"
                        autofocus
                    />
                    <x-filament::icon-button
                        icon="lucide-check"
                        wire:click="saveEdit({{ $node['id'] }}, '{{ $nodeType }}')"
                        size="sm"
                        color="success"
                    />
                    <x-filament::icon-button
                        icon="lucide-x"
                        wire:click="cancelEdit"
                        size="sm"
                        color="gray"
                    />
                </div>
            @else
                <span class="text-sm font-medium">{{ $node['name'] }}</span>
                <span class="text-xs text-gray-500 ml-2">({{ $config['label'] }})</span>
            @endif
        </div>

        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            @if($canHaveChildren && $config['childType'])
                <x-filament::icon-button
                    icon="lucide-plus"
                    wire:click="addChild({{ $node['id'] }}, '{{ $nodeType }}')"
                    size="sm"
                    color="success"
                    tooltip="Add {{ ucfirst($config['childType']) }}"
                />
            @endif

            @if($editingNode !== $node['id'])
                <x-filament::icon-button
                    icon="lucide-pencil"
                    wire:click="startEdit({{ $node['id'] }}, '{{ addslashes($node['name']) }}')"
                    size="sm"
                    color="warning"
                    tooltip="Edit"
                />
            @endif

            <x-filament::icon-button
                icon="lucide-arrow-up"
                wire:click="moveUp({{ $node['id'] }}, '{{ $nodeType }}')"
                size="sm"
                color="gray"
                tooltip="Move Up"
            />

            <x-filament::icon-button
                icon="lucide-arrow-down"
                wire:click="moveDown({{ $node['id'] }}, '{{ $nodeType }}')"
                size="sm"
                color="gray"
                tooltip="Move Down"
            />

            <x-filament::icon-button
                icon="lucide-trash-2"
                wire:click="confirmDelete({{ $node['id'] }}, '{{ $nodeType }}')"
                size="sm"
                color="danger"
                tooltip="Delete"
            />
        </div>
    </div>

    @if($hasChildren && $isExpanded)
        <div class="mt-1 space-y-1">
            @foreach($node['children'] as $child)
                @php
                    $childTypeSlug = $child['type'];
                @endphp
                @include('livewire.training.partials.tree-node', [
                    'node' => $child,
                    'level' => $level + 1,
                    'nodeType' => $childTypeSlug
                ])
            @endforeach
        </div>
    @endif
</div>
