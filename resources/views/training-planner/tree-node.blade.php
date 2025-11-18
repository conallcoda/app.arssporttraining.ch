@php
    $nodeId = $node->getIdentity()?->id ?? 'temp-' . spl_object_id($node);
    $nodeChildren = $depth < $maxDepth ? $node->getChildren() : [];
    $nodeUuid = $node->uuid;
    $isSelected = $selectedPeriodUuid === $nodeUuid;
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
        </span>
    </div>

    @if(isset($expanded[$nodeId]) && count($nodeChildren) > 0)
        <div class="ml-6">
            @foreach($nodeChildren as $child)
                @include('training-planner.tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
