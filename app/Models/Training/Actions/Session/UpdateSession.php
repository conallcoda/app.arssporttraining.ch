<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class UpdateSession extends Action
{
    public function __construct(
        public string $sessionId,
        public ?string $color = null,
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
        $oldColor = $session->data->color;
        $oldExercises = $session->data->exercises;

        $session->data->name = $this->name;
        $session->data->color = $this->color;
        $session->data->exercises = $this->exercises;

        return SessionUpdatedEvent::from([
            'parent' => $parent,
            'session' => $session,
            'oldName' => $oldName,
            'oldColor' => $oldColor,
            'oldExercises' => $oldExercises,
            'newName' => $this->name,
            'newColor' => $this->color,
            'newExercises' => $this->exercises,
        ]);
    }

    public static function fromSessionId(string $sessionId, ?string $color = null, array $exercises = [], ?string $name = null): self
    {
        return new self(
            sessionId: $sessionId,
            color: $color,
            exercises: $exercises,
            name: $name
        );
    }
}
