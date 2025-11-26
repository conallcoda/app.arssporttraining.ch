<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionUpdatedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $session,
        public ?string $oldName,
        public int $oldCategory,
        public array $oldExercises,
        public ?string $newName,
        public int $newCategory,
        public array $newExercises,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->data->name = $this->newName;
        $session->data->category = $this->newCategory;
        $session->data->exercises = $this->newExercises;
    }

    public function undo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->data->name = $this->oldName;
        $session->data->category = $this->oldCategory;
        $session->data->exercises = $this->oldExercises;
    }

    public function label()
    {
        return "Updated Session in {$this->parent->name()}";
    }
}
