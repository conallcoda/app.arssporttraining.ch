<?php

namespace App\Livewire\Training;

use App\Models\Training\Periods\TrainingBlock;
use App\Models\Training\Periods\TrainingExercise;
use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\Periods\TrainingSession;
use App\Models\Training\Periods\TrainingWeek;
use App\Models\Training\TrainingPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Livewire\Component;

class TrainingTreeManager extends Component implements HasForms
{
    use InteractsWithForms;

    public $expandedNodes = [];
    public $seasons = [];
    public $editingNode = null;
    public $editingName = '';
    public $deletingNode = null;
    public $deletingNodeType = null;

    protected $typeMap = [
        'season' => TrainingSeason::class,
        'block' => TrainingBlock::class,
        'week' => TrainingWeek::class,
        'session' => TrainingSession::class,
        'exercise' => TrainingExercise::class,
    ];

    protected $childTypeMap = [
        TrainingSeason::class => TrainingBlock::class,
        TrainingBlock::class => TrainingWeek::class,
        TrainingWeek::class => TrainingSession::class,
        TrainingSession::class => TrainingExercise::class,
    ];

    public function mount()
    {
        $this->loadSeasons();
    }

    public function loadSeasons()
    {
        $this->seasons = TrainingSeason::defaultOrder()
            ->with(['children' => function ($query) {
                $query->defaultOrder()->with(['children' => function ($q) {
                    $q->defaultOrder()->with(['children' => function ($q2) {
                        $q2->defaultOrder()->with(['children' => function ($q3) {
                            $q3->defaultOrder();
                        }]);
                    }]);
                }]);
            }])
            ->get()
            ->toArray();
    }

    public function toggleNode($nodeId)
    {
        if (in_array($nodeId, $this->expandedNodes)) {
            $this->expandedNodes = array_filter($this->expandedNodes, fn($id) => $id !== $nodeId);
        } else {
            $this->expandedNodes[] = $nodeId;
        }
    }

    public function createSeason()
    {
        $season = TrainingSeason::create([
            'name' => 'New Season',
            'type' => 'season',
        ]);

        $this->loadSeasons();
        $this->expandedNodes[] = $season->id;

        Notification::make()
            ->success()
            ->title('Season created')
            ->send();
    }

    public function addChild($parentId, $parentType)
    {
        $parent = $this->typeMap[$parentType]::find($parentId);

        if (!$parent) {
            Notification::make()
                ->danger()
                ->title('Parent not found')
                ->send();
            return;
        }

        $childClass = $this->childTypeMap[get_class($parent)] ?? null;

        if (!$childClass) {
            Notification::make()
                ->danger()
                ->title('Cannot add children to this type')
                ->send();
            return;
        }

        $typeSlug = array_search($childClass, $this->typeMap);
        $name = 'New ' . ucfirst($typeSlug);

        $child = $childClass::create([
            'name' => $name,
            'type' => $typeSlug,
        ]);

        $child->appendToNode($parent)->save();

        $this->loadSeasons();
        $this->expandedNodes[] = $parentId;

        Notification::make()
            ->success()
            ->title(ucfirst($typeSlug) . ' added')
            ->send();
    }

    public function startEdit($nodeId, $currentName)
    {
        $this->editingNode = $nodeId;
        $this->editingName = $currentName;
    }

    public function saveEdit($nodeId, $nodeType)
    {
        $node = $this->typeMap[$nodeType]::find($nodeId);

        if ($node) {
            $node->update(['name' => $this->editingName]);
            $this->loadSeasons();

            Notification::make()
                ->success()
                ->title('Updated')
                ->send();
        }

        $this->editingNode = null;
        $this->editingName = '';
    }

    public function cancelEdit()
    {
        $this->editingNode = null;
        $this->editingName = '';
    }

    public function confirmDelete($nodeId, $nodeType)
    {
        $this->deletingNode = $nodeId;
        $this->deletingNodeType = $nodeType;
    }

    public function deleteNode($nodeId, $nodeType)
    {
        $node = $this->typeMap[$nodeType]::find($nodeId);

        if ($node) {
            $node->delete();
            $this->loadSeasons();

            Notification::make()
                ->success()
                ->title('Deleted')
                ->send();
        }

        $this->deletingNode = null;
        $this->deletingNodeType = null;
    }

    public function cancelDelete()
    {
        $this->deletingNode = null;
        $this->deletingNodeType = null;
    }

    public function moveUp($nodeId, $nodeType)
    {
        $node = $this->typeMap[$nodeType]::find($nodeId);

        if ($node) {
            $node->up();
            $this->loadSeasons();
        }
    }

    public function moveDown($nodeId, $nodeType)
    {
        $node = $this->typeMap[$nodeType]::find($nodeId);

        if ($node) {
            $node->down();
            $this->loadSeasons();
        }
    }

    public function render()
    {
        return view('livewire.training.training-tree-manager');
    }
}
