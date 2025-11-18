<div class="training-tree">
    @foreach($nodes as $node)
        @php
            $nodeId = $node instanceof \App\Models\Training\TrainingPeriodData
                ? ($node->getIdentity()?->id ?? 'temp-' . spl_object_id($node))
                : ($node['id'] ?? 'temp-' . uniqid());
            $nodeLabel = $node instanceof \App\Models\Training\TrainingPeriodData
                ? $node->name()
                : ($node['label'] ?? '');
            $nodeChildren = $node instanceof \App\Models\Training\TrainingPeriodData
                ? $node->getChildren()
                : ($node['children'] ?? []);
        @endphp

        <div class="tree-node">
            <div class="node-header" wire:click="toggle('{{ $nodeId }}')">
                <span class="toggle-icon">
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
                <span class="node-label">{{ $nodeLabel }}</span>
            </div>

            @if(isset($expanded[$nodeId]) && count($nodeChildren) > 0)
                <div class="node-children" style="margin-left: 1.5rem;">
                    <livewire:training.training-tree
                        :nodes="$nodeChildren"
                        :depth="$depth + 1"
                        :key="'tree-' . $nodeId"
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
