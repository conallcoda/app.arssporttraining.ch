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
    public $categories;

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
            }
        } else {
            $this->sessionCategory = $this->categories->first()?->id;
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
    }

    public function saveSession()
    {
        $this->validate([
            'sessionCategory' => 'required|integer',
        ]);

        if ($this->editingSessionUuid) {
            $this->dispatch('updateSession',
                weekUuid: $this->week->uuid,
                sessionUuid: $this->editingSessionUuid,
                category: $this->sessionCategory
            );
        } else {
            $this->dispatch('addSession',
                weekUuid: $this->week->uuid,
                day: $this->sessionDay,
                slot: $this->sessionSlot,
                category: $this->sessionCategory
            );
        }

        $this->closeSessionModal();
    }

    public function moveSession(string $sessionUuid, int $newDay, int $newSlot)
    {
        $this->dispatch('moveSession',
            weekUuid: $this->week->uuid,
            sessionUuid: $sessionUuid,
            newDay: $newDay,
            newSlot: $newSlot
        );
    }

    public function swapSessions(string $sessionUuid1, string $sessionUuid2)
    {
        $this->dispatch('swapSessions',
            weekUuid: $this->week->uuid,
            sessionUuid1: $sessionUuid1,
            sessionUuid2: $sessionUuid2
        );
    }

    public function deleteSession(string $sessionUuid)
    {
        $this->dispatch('deleteSession',
            weekUuid: $this->week->uuid,
            sessionUuid: $sessionUuid
        );

        $this->closeSessionModal();
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

    public function render()
    {
        return view('livewire.training.training-week');
    }

    public function getColorValue(?string $colorName): string
    {
        if (!$colorName) {
            return '#000000';
        }
        return ColorPicker::getColorValue($colorName);
    }
}
