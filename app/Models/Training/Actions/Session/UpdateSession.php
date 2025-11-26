<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class UpdateSession extends Action
{
    public function __construct(
        public string $sessionId,
        public int $category,
        public array $exercises = [],
        public ?string $name = null,
    ) {}

    public function execute(TrainingTree $tree): SessionUpdatedEvent
    {
        $session = $tree->getNode($this->sessionId);

        if (!$session) {
            throw new \Exception("Session node not found");
        }

        $parent = $tree->findParentNode($this->sessionId);

        $oldName = $session->data->name;
        $oldCategory = $session->data->category;
        $oldExercises = $session->data->exercises;

        $session->data->name = $this->name;
        $session->data->category = $this->category;
        $session->data->exercises = $this->exercises;

        return SessionUpdatedEvent::from([
            'parent' => $parent,
            'session' => $session,
            'oldName' => $oldName,
            'oldCategory' => $oldCategory,
            'oldExercises' => $oldExercises,
            'newName' => $this->name,
            'newCategory' => $this->category,
            'newExercises' => $this->exercises
        ]);
    }

    public static function fromSessionId(string $sessionId, int $category, array $exercises = [], ?string $name = null): self
    {
        return new self(
            sessionId: $sessionId,
            category: $category,
            exercises: $exercises,
            name: $name
        );
    }
}
