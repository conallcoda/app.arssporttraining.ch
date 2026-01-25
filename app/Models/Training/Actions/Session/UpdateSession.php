<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class UpdateSession extends Action
{
    public function __construct(
        public string $sessionId,
        public array $exercises = [],
        public ?string $name = null,
    ) {}

    public function execute(TrainingTree $tree): SessionUpdatedEvent
    {
        $session = $tree->getNode($this->sessionId);

        if (! $session) {
            throw new \Exception('Session node not found');
        }

        $parent = $tree->findParentNode($this->sessionId);

        $oldName = $session->data->name;
        $oldExercises = $session->data->exercises;

        $session->data->name = $this->name;
        $session->data->exercises = $this->exercises;

        return SessionUpdatedEvent::from([
            'parent' => $parent,
            'session' => $session,
            'oldName' => $oldName,
            'oldExercises' => $oldExercises,
            'newName' => $this->name,
            'newExercises' => $this->exercises,
        ]);
    }

    public static function fromSessionId(string $sessionId, array $exercises = [], ?string $name = null): self
    {
        return new self(
            sessionId: $sessionId,
            exercises: $exercises,
            name: $name
        );
    }
}
