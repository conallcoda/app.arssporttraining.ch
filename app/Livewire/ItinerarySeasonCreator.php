<?php

namespace App\Livewire;

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Exercise\Exercise;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use App\Models\Training\TrainingTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ItinerarySeasonCreator extends Component
{
    public ItineraryConfig $config;

    public TrainingTree $tree;

    public bool $showSessionModal = false;
    public ?string $editingWeekUuid = null;
    public ?string $editingSessionUuid = null;
    public ?int $editingDay = null;
    public ?int $editingSlot = null;
    public ?int $sessionCategory = null;
    public array $sessionExercises = [];
    public ?string $sessionName = null;



    public function mount()
    {
        $this->config = new ItineraryConfig();
        $this->rebuildTree();
    }

    protected function rebuildTree(): void
    {
        $blocks = [];

        for ($blockIndex = 0; $blockIndex < $this->config->numberOfBlocks; $blockIndex++) {
            $weeks = [];

            for ($weekIndex = 0; $weekIndex < $this->config->weeksPerBlock; $weekIndex++) {
                $weeks[] = new WeekData();
            }

            $blocks[] = (new BlockData())->withChildren($weeks);
        }

        $seasonData = (new SeasonData())->withChildren($blocks);
        $seasonNode = TrainingNode::fromData($seasonData);
        $seasonNode->name = 'New Season';

        $this->tree = TrainingTree::fromTrainingNode($seasonNode);
    }

    #[On('itinerary-config-changed')]
    public function onSeasonConfigChanged(array $config)
    {
        $this->config = ItineraryConfig::from($config);
        $this->rebuildTree();
    }

    public function openSessionModal(string $weekUuid, int $slot, int $day)
    {
        $this->editingWeekUuid = $weekUuid;
        $this->editingDay = $day;
        $this->editingSlot = $slot;

        $existingSession = $this->getSessionAtPosition($weekUuid, $slot, $day);

        if ($existingSession) {
            $this->editingSessionUuid = $existingSession->uuid;
            $this->sessionCategory = $existingSession->data->category;
            $this->sessionExercises = $existingSession->data->exercises ?? [];
            $this->sessionName = $existingSession->data->name;
        } else {
            $this->editingSessionUuid = null;
            $this->sessionCategory = $this->categories->first()?->id;
            $this->sessionExercises = [];
            $this->sessionName = null;
        }

        $this->showSessionModal = true;
    }

    public function closeSessionModal()
    {
        $this->showSessionModal = false;
        $this->editingWeekUuid = null;
        $this->editingSessionUuid = null;
        $this->editingDay = null;
        $this->editingSlot = null;
        $this->sessionCategory = null;
        $this->sessionExercises = [];
        $this->sessionName = null;
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
            'sessionName' => 'nullable|string|max:255',
        ]);

        $exercises = array_values(array_filter($this->sessionExercises, fn($id) => $id !== null));

        if ($this->editingSessionUuid) {
            $this->tree->executeAction('session.update', [
                'sessionId' => $this->editingSessionUuid,
                'category' => $this->sessionCategory,
                'exercises' => $exercises,
                'name' => $this->sessionName,
            ]);

            $this->syncSessionAcrossBlocks($this->editingWeekUuid, $this->editingSlot, $this->editingDay);
        } else {
            foreach ($this->tree->root->children as $block) {
                $week = $this->getWeekAtIndex($block, $this->getWeekIndex($this->editingWeekUuid));
                if ($week) {
                    $this->tree->executeAction('session.add', [
                        'parentId' => $week->uuid,
                        'day' => $this->editingDay,
                        'slot' => $this->editingSlot,
                        'category' => $this->sessionCategory,
                        'exercises' => $exercises,
                        'name' => $this->sessionName,
                    ]);
                }
            }
        }

        $this->closeSessionModal();
    }

    public function deleteSession()
    {
        if ($this->editingSessionUuid && $this->editingWeekUuid) {
            foreach ($this->tree->root->children as $block) {
                $weekIndex = $this->getWeekIndex($this->editingWeekUuid);
                $week = $this->getWeekAtIndex($block, $weekIndex);

                if ($week) {
                    $session = $this->getSessionAtPosition($week->uuid, $this->editingSlot, $this->editingDay);
                    if ($session) {
                        $this->tree->executeAction('session.delete', [
                            'parentId' => $week->uuid,
                            'sessionId' => $session->uuid,
                        ]);
                    }
                }
            }
        }
        $this->closeSessionModal();
    }

    protected function syncSessionAcrossBlocks(string $sourceWeekUuid, int $slot, int $day): void
    {
        $sourceSession = $this->getSessionAtPosition($sourceWeekUuid, $slot, $day);
        if (!$sourceSession) {
            return;
        }

        $weekIndex = $this->getWeekIndex($sourceWeekUuid);

        foreach ($this->tree->root->children as $block) {
            $week = $this->getWeekAtIndex($block, $weekIndex);
            if (!$week || $week->uuid === $sourceWeekUuid) {
                continue;
            }

            $existingSession = $this->getSessionAtPosition($week->uuid, $slot, $day);
            if ($existingSession) {
                $this->tree->executeAction('session.update', [
                    'sessionId' => $existingSession->uuid,
                    'category' => $sourceSession->data->category,
                    'exercises' => $sourceSession->data->exercises,
                    'name' => $sourceSession->data->name,
                ]);
            } else {
                $this->tree->executeAction('session.add', [
                    'parentId' => $week->uuid,
                    'day' => $day,
                    'slot' => $slot,
                    'category' => $sourceSession->data->category,
                    'exercises' => $sourceSession->data->exercises,
                    'name' => $sourceSession->data->name,
                ]);
            }
        }
    }

    protected function getWeekIndex(string $weekUuid): int
    {
        foreach ($this->tree->root->children as $block) {
            foreach ($block->children as $index => $week) {
                if ($week->uuid === $weekUuid) {
                    return $index;
                }
            }
        }
        return 0;
    }

    protected function getWeekAtIndex(TrainingNode $block, int $index): ?TrainingNode
    {
        return $block->children[$index] ?? null;
    }

    protected function getSessionAtPosition(string $weekUuid, int $slot, int $day): ?TrainingNode
    {
        $week = $this->tree->getNode($weekUuid);
        if (!$week) {
            return null;
        }

        foreach ($week->children as $session) {
            if ($session->data->day === $day && $session->data->slot === $slot) {
                return $session;
            }
        }

        return null;
    }

    public function getItinerary(string $weekUuid, int $slot, int $day): ?int
    {
        $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
        return $session?->data->category;
    }

    public function getSessionName(string $weekUuid, int $slot, int $day): ?string
    {
        $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
        return $session?->data->name;
    }

    #[On('action')]
    public function action($type, $action, $params = [])
    {
        $this->tree->executeAction("$type.$action", $params);
    }

    public function getColorValue(?string $colorName): string
    {
        if (!$colorName) {
            return '#000000';
        }
        return ColorPicker::getColorValue($colorName);
    }

    #[Computed]
    public function categories()
    {
        return TrainingSessionCategory::all();
    }

    #[Computed]
    public function categoriesById()
    {
        return $this->categories->keyBy('id');
    }

    #[Computed]
    public function exercises()
    {
        return Exercise::orderBy('name')->get();
    }

    #[Computed]
    public function days(): array
    {
        return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }

    #[Computed]
    public function daysFull(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    }

    #[Computed]
    public function slots(): array
    {
        return ['Morning', 'Afternoon'];
    }

    #[Computed]
    public function firstBlock(): ?TrainingNode
    {
        return $this->tree->root->children[0] ?? null;
    }

    public function render()
    {
        return view('livewire.itinerary-season-creator');
    }
}
