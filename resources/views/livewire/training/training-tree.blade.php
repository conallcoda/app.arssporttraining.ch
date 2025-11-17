<div class="training-tree">
    @foreach($nodes as $node)
        <div class="tree-node">
            <div class="node-header" wire:click="toggle('{{ $node['id'] }}')">
                <span class="toggle-icon">
                    @if(isset($node['children']) && count($node['children']) > 0)
                        @if(isset($expanded[$node['id']]))
                            <x-lucide-chevron-down class="w-4 h-4" />
                        @else
                            <x-lucide-chevron-right class="w-4 h-4" />
                        @endif
                    @else
                        <span class="w-4 h-4 opacity-0"></span>
                    @endif
                </span>
                <span class="node-label">{{ $node['label'] }}</span>
            </div>

            @if(isset($expanded[$node['id']]) && isset($node['children']))
                <div class="node-children" style="margin-left: 1.5rem;">
                    <livewire:training.training-tree
                        :nodes="$node['children']"
                        :depth="$depth + 1"
                        :key="'tree-' . $node['id']"
                    />
                </div>
            @endif
        </div>
    @endforeach

    <style>
        .training-tree {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 0.875rem;
        }
        .tree-node {
            margin: 0.25rem 0;
        }
        .node-header {
            display: flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            border-radius: 0.25rem;
            transition: background-color 0.15s;
        }
        .node-header:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .toggle-icon {
            display: inline-flex;
            align-items: center;
            margin-right: 0.5rem;
            user-select: none;
        }
        .node-label {
            user-select: none;
        }
    </style>
</div>
