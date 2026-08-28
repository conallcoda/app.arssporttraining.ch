<?php

namespace App\Livewire\Training\View;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProgramRecordEditor extends Component
{
    public ExerciseProgram $exerciseProgram;

    #[Reactive]
    public ?int $scheduledTrainingProgramId = null;

    #[Reactive]
    public ?int $userId = null;

    public ?string $sessionKey = null;

    public ?string $section = null;

    public ?int $exerciseId = null;

    public ?int $exerciseSort = null;

    public int $openVersion = 0;

    public bool $open = false;

    public ?int $refreshTrainingProgramId = null;

    public ?int $refreshProgramExerciseId = null;

    #[On('open-program-record-at-session')]
    public function openAtSession(string $sessionKey, string $section, int $exerciseId, int $exerciseSort): void
    {
        if ($this->userId === null || $this->scheduledTrainingProgramId === null || ! $this->canRecord()) {
            return;
        }

        $slot = TrainingProgramSlot::query()
            ->whereKey((int) $sessionKey)
            ->where('training_program_id', $this->scheduledTrainingProgramId)
            ->where('user_id', $this->userId)
            ->first();

        if (! $slot instanceof TrainingProgramSlot) {
            return;
        }

        $this->sessionKey = (string) $slot->id;
        $this->section = $section;
        $this->exerciseId = $exerciseId;
        $this->exerciseSort = $exerciseSort;
        $this->open = true;
        $this->openVersion++;

        unset($this->recordingSlot);
        Flux::modal($this->modalName())->show();
    }

    #[On('delegated-exercise-editor-closed')]
    public function closeEditor(
        bool $saved = false,
        ?int $trainingProgramId = null,
        ?int $programExerciseId = null,
    ): void {
        if (! $this->open) {
            return;
        }

        if ($saved) {
            $this->refreshTrainingProgramId = $trainingProgramId;
            $this->refreshProgramExerciseId = $programExerciseId;
        }

        Flux::modal($this->modalName())->close();
    }

    public function flyoutClosed(): void
    {
        $trainingProgramId = $this->refreshTrainingProgramId;
        $programExerciseId = $this->refreshProgramExerciseId;

        $this->open = false;
        $this->sessionKey = null;
        $this->section = null;
        $this->exerciseId = null;
        $this->exerciseSort = null;
        $this->refreshTrainingProgramId = null;
        $this->refreshProgramExerciseId = null;

        unset($this->recordingSlot);

        if ($trainingProgramId !== null && $programExerciseId !== null) {
            $this->dispatch(
                'training-session-record-updated',
                trainingProgramId: $trainingProgramId,
                programExerciseId: $programExerciseId,
            )->to(PlanExerciseGrid::class);
        }
    }

    #[Computed]
    public function recordingSlot(): ?TrainingProgramSlot
    {
        if (! $this->open || $this->sessionKey === null || $this->scheduledTrainingProgramId === null || $this->userId === null) {
            return null;
        }

        return TrainingProgramSlot::query()
            ->with('trainingProgram')
            ->whereKey((int) $this->sessionKey)
            ->where('training_program_id', $this->scheduledTrainingProgramId)
            ->where('user_id', $this->userId)
            ->first();
    }

    #[Computed]
    public function athlete(): ?User
    {
        return $this->userId === null ? null : User::query()->find($this->userId);
    }

    public function modalName(): string
    {
        return 'program-recording-'.$this->exerciseProgram->id;
    }

    protected function canRecord(): bool
    {
        if (! in_array(auth()->user()?->type, [UserTypeEnum::Coach, UserTypeEnum::Admin], true)) {
            return false;
        }

        return true;
    }

    public function render(): View
    {
        return view('livewire.training.view.program-record-editor');
    }
}
