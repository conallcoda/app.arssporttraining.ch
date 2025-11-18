@php
    $nodeId = $node->getIdentity()?->id ?? 'temp-' . spl_object_id($node);
    $nodeChildren = $depth < $maxDepth ? $node->getChildren() : [];
    $nodeUuid = $node->uuid;
    $isSelected = $selectedPeriodUuid === $nodeUuid;
    $nodeType = $node->type;
    $isFirst = $isFirst ?? false;
    $isLast = $isLast ?? false;
@endphp

<div class="my-1 text-sm">
    <div class="flex items-center py-1 px-2 rounded transition-colors select-none {{ $isSelected ? 'bg-blue-100 dark:bg-blue-900' : 'hover:bg-black/5' }}">
        <span class="inline-flex items-center mr-2 select-none cursor-pointer"
              wire:click.stop="toggle('{{ $nodeId }}')">
            @if(count($nodeChildren) > 0)
                @if(isset($expanded[$nodeId]))
                    <x-lucide-chevron-down class="w-4 h-4" />
                @else
                    <x-lucide-chevron-right class="w-4 h-4" />
                @endif
            @else
                <span class="w-4 h-4 opacity-0"></span>
            @endif
        </span>
        <span class="select-none cursor-pointer flex-1 {{ $isSelected ? 'font-semibold text-blue-700 dark:text-blue-300' : '' }}"
              wire:click.stop="selectPeriod('{{ $nodeUuid }}')">
            {{ $node->name() }}
            @if($nodeType === 'week' && count($node->children) > 0)
                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-medium rounded-full bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                    {{ count($node->children) }}
                </span>
            @endif
        </span>
        @if($nodeType === 'season')
            <flux:dropdown position="bottom" align="end">
                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" inset="top bottom" class="ml-2" />
                <flux:menu>
                    <flux:menu.item wire:click="addBlock('{{ $nodeUuid }}')" icon="list-plus">Add Block</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @elseif($nodeType === 'block')
            <flux:dropdown position="bottom" align="end">
                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" inset="top bottom" class="ml-2" />
                <flux:menu>
                    <flux:menu.item wire:click="addWeek('{{ $nodeUuid }}')" icon="list-plus">Add Week</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="duplicatePeriod('{{ $nodeUuid }}')" icon="copy-plus">Duplicate</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="moveUp('{{ $nodeUuid }}')" icon="arrow-up" :disabled="$isFirst">Move Up</flux:menu.item>
                    <flux:menu.item wire:click="moveDown('{{ $nodeUuid }}')" icon="arrow-down" :disabled="$isLast">Move Down</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="deletePeriod('{{ $nodeUuid }}')" icon="trash" variant="danger">Delete</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @elseif($nodeType === 'week')
            <flux:dropdown position="bottom" align="end">
                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" inset="top bottom" class="ml-2" />
                <flux:menu>
                    <flux:menu.item wire:click="duplicatePeriod('{{ $nodeUuid }}')" icon="copy-plus">Duplicate</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="moveUp('{{ $nodeUuid }}')" icon="arrow-up" :disabled="$isFirst">Move Up</flux:menu.item>
                    <flux:menu.item wire:click="moveDown('{{ $nodeUuid }}')" icon="arrow-down" :disabled="$isLast">Move Down</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="deletePeriod('{{ $nodeUuid }}')" icon="trash" variant="danger">Delete</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    @if(isset($expanded[$nodeId]) && count($nodeChildren) > 0)
        <div class="ml-6">
            @foreach($nodeChildren as $index => $child)
                @include('training-planner.tree-node', [
                    'node' => $child,
                    'depth' => $depth + 1,
                    'isFirst' => $index === 0,
                    'isLast' => $index === count($nodeChildren) - 1
                ])
            @endforeach
        </div>
    @endif
</div>
