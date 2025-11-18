@php
    $nodeId = $node->getIdentity()?->id ?? 'temp-' . spl_object_id($node);
    $nodeChildren = $depth < $maxDepth ? $node->getChildren() : [];
    $nodeType = $node->type;
    $isWeek = $nodeType === 'week';
@endphp

<div class="my-1 text-sm">
    <div class="flex items-center py-1 px-2 cursor-pointer rounded transition-colors hover:bg-black/5 select-none"
         wire:click="{{ $isWeek ? "selectWeek('{$nodeId}')" : "toggle('{$nodeId}')" }}">
        <span class="inline-flex items-center mr-2 select-none">
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
        <span class="select-none">{{ $node->name() }}</span>
    </div>

    @if(isset($expanded[$nodeId]) && count($nodeChildren) > 0)
        <div class="ml-6">
            @foreach($nodeChildren as $child)
                @include('training-planner.tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
