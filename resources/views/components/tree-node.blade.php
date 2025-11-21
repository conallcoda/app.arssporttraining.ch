@php
    $nodeChildren = $node->children ?? [];
    $nodeUuid = $node->uuid;
    $isSelected = $selectedUuid === $nodeUuid;
    $level = $level ?? 0;
@endphp

<div class="{{ $level > 0 ? 'my-1' : '' }} text-sm">
    <div class="flex items-center py-1 px-2 rounded transition-colors select-none {{ $isSelected ? 'bg-blue-100 dark:bg-blue-900' : 'hover:bg-black/5' }}">
        <span class="inline-flex items-center mr-2 select-none cursor-pointer"
              @if(count($nodeChildren) > 0)
                  @click="toggle('{{ $nodeUuid }}')"
              @endif>
            @if(count($nodeChildren) > 0)
                <x-lucide-chevron-down class="w-4 h-4 transition-transform" ::class="{ '-rotate-90': !isExpanded('{{ $nodeUuid }}') }" />
            @else
                <span class="w-4 h-4 opacity-0"></span>
            @endif
        </span>

        <span class="select-none cursor-pointer flex-1 {{ $isSelected ? 'font-semibold text-blue-700 dark:text-blue-300' : '' }}"
              wire:click.stop="selectNode('{{ $nodeUuid }}')">
            {{ $node->label }}
            @if($node->badge)
                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-medium rounded-full bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                    {{ $node->badge }}
                </span>
            @endif
        </span>

        @if(isset($actions[$nodeUuid]) && count($actions[$nodeUuid]) > 0)
            <flux:dropdown position="bottom" align="end">
                <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" inset="top bottom" class="ml-2" />
                <flux:menu>
                    @foreach($actions[$nodeUuid] as $index => $action)
                        @if($action->separator && $index > 0)
                            <flux:menu.separator />
                        @endif
                        <flux:menu.item
                            wire:click="action('{{ $action->type() }}', '{{ $action->action() }}', {{ json_encode(array_merge(['nodeId' => $nodeUuid], $action->metadata)) }})"
                            :icon="$action->icon ?? ''"
                            :variant="$action->variant ?? 'default'"
                            :disabled="$action->disabled">
                            {{ $action->label }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    @if(count($nodeChildren) > 0)
        <div class="ml-6" x-show="isExpanded('{{ $nodeUuid }}')" x-collapse>
            @foreach($nodeChildren as $child)
                @include('components.tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
