<?php

namespace App\Livewire\Training;

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use Livewire\Component;

class TrainingWeek extends Component
{
    public TrainingNode $week;
    public bool $showSessionModal = false;
    public ?string $editingSessionUuid = null;
    public ?int $sessionCategory = null;
    public ?int $sessionDay = null;
    public ?int $sessionSlot = null;
    public array $sessionExercises = [];
    public $categories;

    public ?string $selectedSessionUuid = null;

    public function mount()
    {
        $this->categories = TrainingSessionCategory::all();
    }

    public function openSessionModal(?string $sessionUuid, int $day, int $slot)
    {
        $this->resetValidation();

        $this->editingSessionUuid = $sessionUuid;
        $this->sessionDay = $day;
        $this->sessionSlot = $slot;

        if ($sessionUuid) {
            $session = $this->findSession($sessionUuid);
            if ($session) {
                $this->sessionCategory = $session->data->category;
                $this->sessionExercises = $session->data->exercises ?? [];
            }
        } else {
            $this->sessionCategory = $this->categories->first()?->id;
            $this->sessionExercises = [];
        }

        $this->showSessionModal = true;
    }

    public function closeSessionModal()
    {
        $this->resetValidation();
        $this->showSessionModal = false;
        $this->editingSessionUuid = null;
        $this->sessionCategory = null;
        $this->sessionDay = null;
        $this->sessionSlot = null;
        $this->sessionExercises = [];
    }

    public function addExercise()
    {
        $this->sessionExercises[] = null;
    }

    public function removeExercise(int $index)
    {
        unset($this->sessionExercises[$index]);
        $this->sessionExercises = array_values($this->sessionExercises);
    }

    public function moveExerciseUp(int $index)
    {
        if ($index > 0 && isset($this->sessionExercises[$index]) && isset($this->sessionExercises[$index - 1])) {
            $temp = $this->sessionExercises[$index - 1];
            $this->sessionExercises[$index - 1] = $this->sessionExercises[$index];
            $this->sessionExercises[$index] = $temp;
        }
    }

    public function moveExerciseDown(int $index)
    {
        if ($index < count($this->sessionExercises) - 1 && isset($this->sessionExercises[$index]) && isset($this->sessionExercises[$index + 1])) {
            $temp = $this->sessionExercises[$index + 1];
            $this->sessionExercises[$index + 1] = $this->sessionExercises[$index];
            $this->sessionExercises[$index] = $temp;
        }
    }

    public function saveSession()
    {
        $this->validate([
            'sessionCategory' => 'required|integer',
            'sessionExercises.*' => 'nullable|integer|exists:exercises,id',
        ]);

        $exercises = array_filter($this->sessionExercises, fn($id) => $id !== null);

        if ($this->editingSessionUuid) {
            $this->dispatch('action',
                type: 'session',
                action: 'update',
                params: [
                    'sessionId' => $this->editingSessionUuid,
                    'category' => $this->sessionCategory,
                    'exercises' => array_values($exercises)
                ]
            );
        } else {
            $this->dispatch('action',
                type: 'session',
                action: 'add',
                params: [
                    'parentId' => $this->week->uuid,
                    'day' => $this->sessionDay,
                    'slot' => $this->sessionSlot,
                    'category' => $this->sessionCategory,
                    'exercises' => array_values($exercises)
                ]
            );
        }

        $this->closeSessionModal();
    }

    public function moveSession(string $sessionUuid, int $newDay, int $newSlot)
    {
        $this->dispatch('action',
            type: 'session',
            action: 'move',
            params: [
                'sessionId' => $sessionUuid,
                'newDay' => $newDay,
                'newSlot' => $newSlot
            ]
        );
    }

    public function swapSessions(string $sessionUuid1, string $sessionUuid2)
    {
        $this->dispatch('action',
            type: 'session',
            action: 'swap',
            params: [
                'session1Id' => $sessionUuid1,
                'session2Id' => $sessionUuid2
            ]
        );
    }

    public function deleteSession(string $sessionUuid)
    {
        $this->dispatch('action',
            type: 'session',
            action: 'delete',
            params: [
                'parentId' => $this->week->uuid,
                'sessionId' => $sessionUuid
            ]
        );

        $this->closeSessionModal();
    }

    public function selectSession(?string $sessionUuid)
    {
        $this->selectedSessionUuid = $sessionUuid;
    }

    public function addExerciseToSession(string $sessionUuid, int $exerciseId)
    {
        $this->dispatch('action',
            type: 'exercise',
            action: 'add',
            params: [
                'parentId' => $sessionUuid,
                'exercise' => $exerciseId,
            ]
        );
    }

    public function updateExerciseNode(string $exerciseUuid, int $exerciseId)
    {
        $this->dispatch('action',
            type: 'exercise',
            action: 'update',
            params: [
                'exerciseId' => $exerciseUuid,
                'exercise' => $exerciseId,
            ]
        );
    }

    public function deleteExerciseNode(string $exerciseUuid)
    {
        $this->dispatch('action',
            type: 'exercise',
            action: 'delete',
            params: [
                'nodeId' => $exerciseUuid,
            ]
        );
    }

    public function addSetToExercise(string $exerciseUuid, int $reps = 10, float $weight = 0)
    {
        $this->dispatch('action',
            type: 'set',
            action: 'add',
            params: [
                'parentId' => $exerciseUuid,
                'reps' => $reps,
                'weight' => $weight,
            ]
        );
    }

    public function updateSet(string $setUuid, int $reps, float $weight)
    {
        $this->dispatch('action',
            type: 'set',
            action: 'update',
            params: [
                'setId' => $setUuid,
                'reps' => $reps,
                'weight' => $weight,
            ]
        );
    }

    public function deleteSet(string $setUuid)
    {
        $this->dispatch('action',
            type: 'set',
            action: 'delete',
            params: [
                'nodeId' => $setUuid,
            ]
        );
    }

    protected function findSession(string $uuid): ?TrainingNode
    {
        foreach ($this->week->children as $session) {
            if ($session->uuid === $uuid) {
                return $session;
            }
        }
        return null;
    }

    public function getSelectedSession(): ?TrainingNode
    {
        if (!$this->selectedSessionUuid) {
            return null;
        }
        return $this->findSession($this->selectedSessionUuid);
    }

    public function render()
    {
        return view('livewire.training.training-week', [
            'selectedSession' => $this->getSelectedSession(),
        ]);
    }

    public function getColorValue(?string $colorName): string
    {
        if (!$colorName) {
            return '#000000';
        }
        return ColorPicker::getColorValue($colorName);
    }
}
