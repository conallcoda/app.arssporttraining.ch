<?php

namespace App\Livewire\Training;

use App\Models\Training\TrainingPeriodData;
use Livewire\Component;

class TrainingTree extends Component
{
    public $nodes = [];
    public $expanded = [];
    public $depth = 0;

    public function mount($nodes = [], $depth = 0)
    {
        $this->nodes = $nodes;
        $this->depth = $depth;

        if ($this->depth < 2) {
            foreach ($this->nodes as $node) {
                $nodeId = $this->getNodeId($node);
                $this->expanded[$nodeId] = true;
            }
        }
    }

    protected function getNodeId($node)
    {
        if ($node instanceof TrainingPeriodData) {
            return $node->getIdentity()?->id ?? 'temp-' . spl_object_id($node);
        }
        return $node['id'] ?? 'temp-' . uniqid();
    }

    public function toggle($nodeId)
    {
        if (isset($this->expanded[$nodeId])) {
            unset($this->expanded[$nodeId]);
        } else {
            $this->expanded[$nodeId] = true;
        }
    }

    public function render()
    {
        return view('livewire.training.training-tree');
    }
}
