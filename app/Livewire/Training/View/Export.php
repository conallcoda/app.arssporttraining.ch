<?php

namespace App\Livewire\Training\View;

use App\Cms\Livewire\Concerns\InteractsWithParentView;
use App\Data\Training\DefaultTrainingProgramData;
use App\Data\Training\UserTrainingProgramData;
use App\Exports\UserTrainingPlanExport;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgramExercise;
use App\Models\Users\User;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\ProgramTrainingPlan;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSet;
use App\Training\Services\TrainingBlockGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class Export extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    public array $selectedUserIds = [];

    #[Url(as: 'preview_user', except: null)]
    public ?int $previewUserId = null;

    public bool $exporting = false;

    public function mount(
        TrainingPlan $trainingPlan,
        Collection $programs,
        Collection $users,
    ): void {
        $this->trainingPlan = $trainingPlan;
        $this->programs = $programs;
        $this->users = $users;
        $this->syncSelectedFromPreview();
    }

    #[On('tab-changed')]
    public function handleTabChanged(string $tab): void
    {
        if ($tab === 'export') {
            $this->trainingPlan->refresh();
            unset($this->exportableUserIds);
            $this->syncSelectedFromPreview();
            unset($this->previewPlans, $this->previewUser, $this->selectedUsers);
        }
    }

    #[On('plan-user-changed')]
    public function handlePlanUserChanged(?int $userId): void
    {
        $this->trainingPlan->refresh();
        unset($this->previewPlans, $this->exportableUserIds);
    }

    protected function syncSelectedFromPreview(): void
    {
        if ($this->users->isEmpty()) {
            return;
        }

        $this->selectedUserIds = $this->exportableUserIds;

        $validUserIds = $this->users->pluck('id')->all();
        if ($this->previewUserId && ! in_array($this->previewUserId, $validUserIds)) {
            $this->previewUserId = null;
        }
    }

    #[Computed]
    public function previewUser(): ?User
    {
        if ($this->previewUserId) {
            return $this->users->firstWhere('id', $this->previewUserId);
        }

        if (! empty($this->selectedUserIds)) {
            return $this->users->firstWhere('id', $this->selectedUserIds[0]);
        }

        return null;
    }

    #[Computed]
    public function selectedUsers(): Collection
    {
        return $this->users->whereIn('id', $this->selectedUserIds);
    }

    #[Computed]
    public function exportableUserIds(): array
    {
        return $this->users
            ->filter(fn (User $user) => $this->userHasValidTrainingData($user))
            ->pluck('id')
            ->all();
    }

    public function userHasValidTrainingData(User $user): bool
    {
        $athleteData = $this->getAthleteTrainingData($user->id);

        return $athleteData->measuredReps !== null && $athleteData->measuredWeight !== null;
    }

    public function updatedSelectedUserIds(): void
    {
        unset($this->selectedUsers, $this->previewPlans);
    }

    public function updatedPreviewUserId(): void
    {
        unset($this->previewUser, $this->previewPlans);

        $validUserIds = $this->users->pluck('id')->all();

        if ($this->previewUserId && ! in_array($this->previewUserId, $validUserIds)) {
            $this->previewUserId = null;
        }
    }

    #[Computed]
    public function previewPlans(): array
    {
        $user = $this->previewUser;
        if (! $user) {
            return [];
        }

        return $this->generateGroupedPlansForUser($user);
    }

    protected function getWeeksForUser(int $userId): int
    {
        return count($this->getScheduleWeeksForUser($userId));
    }

    protected function getScheduleWeeksForUser(int $userId): array
    {
        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        return collect($defaultWeeks)->map(function ($week) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $userOverrides[$weekId] ?? null;

            if (! $override) {
                return $week;
            }

            if (array_key_exists('linkedToWeekId', $override)) {
                $week['linkedToWeekId'] = $override['linkedToWeekId'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->all();
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn ($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    public function selectAll(): void
    {
        $this->selectedUserIds = $this->exportableUserIds;
    }

    public function deselectAll(): void
    {
        $this->selectedUserIds = [];
    }

    public function export(): StreamedResponse|BinaryFileResponse
    {
        $this->exporting = true;

        $selectedUsers = $this->users->whereIn('id', $this->selectedUserIds);

        if ($selectedUsers->isEmpty()) {
            $this->exporting = false;

            return response()->streamDownload(function () {
                echo '';
            }, 'empty.zip');
        }

        if ($selectedUsers->count() === 1) {
            return $this->exportSingleUser($selectedUsers->first());
        }

        return $this->exportMultipleUsers($selectedUsers);
    }

    protected function exportSingleUser(User $user): BinaryFileResponse|StreamedResponse
    {
        if (! $this->userHasValidTrainingData($user)) {
            $this->exporting = false;

            return response()->streamDownload(function () {
                echo '';
            }, 'empty.xlsx');
        }

        $plans = $this->generateGroupedPlansForUser($user);

        if (empty($plans)) {
            $this->exporting = false;

            return response()->streamDownload(function () {
                echo '';
            }, 'empty.xlsx');
        }

        $filename = $this->sanitizeFilename($user->name).'_training_plan.xlsx';

        $this->exporting = false;

        return Excel::download(
            new UserTrainingPlanExport($user, $plans),
            $filename
        );
    }

    protected function exportMultipleUsers(Collection $users): StreamedResponse
    {
        $uniqueId = uniqid();
        $tempDir = 'temp/exports/'.$uniqueId;
        Storage::disk('local')->makeDirectory($tempDir);

        $files = [];

        foreach ($users as $user) {
            if (! $this->userHasValidTrainingData($user)) {
                continue;
            }

            $plans = $this->generateGroupedPlansForUser($user);

            if (empty($plans)) {
                continue;
            }

            $filename = $this->sanitizeFilename($user->name).'_training_plan.xlsx';

            Excel::store(
                new UserTrainingPlanExport($user, $plans),
                $tempDir.'/'.$filename,
                'local'
            );

            $files[] = Storage::disk('local')->path($tempDir.'/'.$filename);
        }

        $zipFilename = 'training_plans.zip';
        $zipPath = Storage::disk('local')->path($tempDir.'/'.$zipFilename);
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        $this->exporting = false;

        return response()->streamDownload(function () use ($zipPath, $tempDir) {
            readfile($zipPath);
            Storage::disk('local')->deleteDirectory($tempDir);
        }, 'training_plans_'.date('Y-m-d_His').'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    protected function generateGroupedPlansForUser(User $user): array
    {
        $plans = [];
        $athleteData = $this->getAthleteTrainingData($user->id);

        if ($athleteData->measuredReps === null || $athleteData->measuredWeight === null) {
            return $plans;
        }

        $selectedProgramIds = $this->getSelectedProgramIds($user->id);
        $startDate = $this->getStartDateForUser($user->id);

        foreach ($this->programs as $program) {
            if (! in_array($program->id, $selectedProgramIds)) {
                continue;
            }
            $exercises = [];

            foreach ($program->exercises as $exercise) {
                $pivotConfig = $this->getPivotConfig($program->id, $exercise->id);
                $config = $this->getExerciseConfig($user->id, $exercise->id, $pivotConfig);
                $block = $this->generateBlockForUserAndExercise($user, $exercise, $athleteData, $config, $program->id);

                if ($block) {
                    $weekOverrides = $this->getWeekOverridesForExport($user->id, $exercise->id);
                    $exercises[] = [
                        'exercise' => $exercise,
                        'block' => $block,
                        'tut' => $config['tut'],
                        'rest' => (int) $config['rest'],
                        'weekOverrides' => $weekOverrides,
                    ];
                }
            }

            if (! empty($exercises)) {
                $plans[] = new ProgramTrainingPlan(
                    user: $user,
                    programName: $program->name,
                    exercises: $exercises,
                    startDate: $startDate
                );
            }
        }

        return $plans;
    }

    protected function getStartDateForUser(int $userId): string
    {
        $userData = UserTrainingProgramData::fromTrainingPlan($this->trainingPlan, $userId);
        $defaultData = DefaultTrainingProgramData::fromTrainingPlan($this->trainingPlan);

        return $userData->startDate ?? $defaultData->startDate;
    }

    protected function getAthleteTrainingData(int $userId): UserTrainingProgramData
    {
        return UserTrainingProgramData::fromTrainingPlan($this->trainingPlan, $userId);
    }

    protected function getSelectedProgramIds(int $userId): array
    {
        $programIds = [];
        $weeks = $this->getScheduleWeeksForUser($userId);

        foreach ($weeks as $week) {
            $slots = $this->getResolvedSlotsForWeek($week, $weeks);
            foreach ($slots as $slot) {
                $programId = $slot['programId'] ?? null;
                if ($programId !== null && ! in_array($programId, $programIds)) {
                    $programIds[] = $programId;
                }
            }
        }

        return $programIds;
    }

    protected function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if ($week['linkedToWeekId'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    protected function getSessionsPerWeekForProgram(int $userId, int $programId): int
    {
        $weeks = $this->getScheduleWeeksForUser($userId);

        if (empty($weeks)) {
            return 2;
        }

        $firstWeek = $weeks[0] ?? null;
        if (! $firstWeek) {
            return 2;
        }

        $slots = $this->getResolvedSlotsForWeek($firstWeek, $weeks);
        $count = 0;

        foreach ($slots as $slot) {
            if (($slot['programId'] ?? null) === $programId) {
                $count++;
            }
        }

        return max(1, $count);
    }

    protected function generateBlockForUserAndExercise(
        User $user,
        mixed $exercise,
        UserTrainingProgramData $athleteData,
        array $config,
        int $programId
    ): ?TrainingBlock {
        $generator = new TrainingBlockGenerator;
        $sessionsPerWeek = $this->getSessionsPerWeekForProgram($user->id, $programId);

        $block = $generator->generate(
            measuredWeight: $athleteData->measuredWeight,
            measuredReps: $athleteData->measuredReps,
            oneRepMaxModifier: $config['oneRepMaxModifier'],
            targetPercentage: $config['target'],
            startingReps: $config['startingReps'],
            sets: $config['sets'],
            weeks: $this->getWeeksForUser($user->id),
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: true,
            deloadSetsReduction: 1,
        );

        return $this->applyCellOverrides($block, $user->id, $exercise->id);
    }

    protected function getPivotConfig(int $programId, int $exerciseId): array
    {
        $pivot = TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $programId)
            ->where('exercise_id', $exerciseId)
            ->first();

        if (! $pivot) {
            return [];
        }

        $configData = $pivot->config;

        if ($configData instanceof \Spatie\SchemalessAttributes\SchemalessAttributes) {
            return $configData->all();
        }

        if (is_array($configData)) {
            return $configData;
        }

        return [];
    }

    protected function getExerciseConfig(int $userId, int $exerciseId, array $pivotConfig): array
    {
        $defaultData = DefaultTrainingProgramData::fromTrainingPlan($this->trainingPlan);

        $systemTarget = $defaultData->targetGoal;
        $systemStartingReps = $pivotConfig['startingReps'] ?? ExerciseOverrideData::DEFAULT_STARTING_REPS;
        $systemSets = $pivotConfig['sets'] ?? ExerciseOverrideData::DEFAULT_SETS;
        $systemTut = $pivotConfig['tut'] ?? ExerciseOverrideData::DEFAULT_TUT;
        $systemRest = $pivotConfig['rest'] ?? ExerciseOverrideData::DEFAULT_REST;
        $oneRepMaxModifier = $pivotConfig['oneRepMaxModifier'] ?? 100;

        $allDefaultExercises = $this->trainingPlan->config->get('users.default.exercises', []);
        $allUserExercises = $this->trainingPlan->config->get("users.{$userId}.exercises", []);

        $defaultOverride = $allDefaultExercises[$exerciseId] ?? $allDefaultExercises[(string) $exerciseId] ?? [];
        $userOverride = $allUserExercises[$exerciseId] ?? $allUserExercises[(string) $exerciseId] ?? [];

        return [
            'target' => $userOverride['target'] ?? $defaultOverride['target'] ?? $systemTarget,
            'startingReps' => $userOverride['startingReps'] ?? $defaultOverride['startingReps'] ?? $systemStartingReps,
            'sets' => $userOverride['sets'] ?? $defaultOverride['sets'] ?? $systemSets,
            'tut' => $userOverride['tut'] ?? $defaultOverride['tut'] ?? $systemTut,
            'rest' => $userOverride['rest'] ?? $defaultOverride['rest'] ?? $systemRest,
            'oneRepMaxModifier' => $oneRepMaxModifier,
        ];
    }

    protected function applyCellOverrides(TrainingBlock $block, int $userId, int $exerciseId): TrainingBlock
    {
        $allDefaultCells = $this->trainingPlan->config->get('users.default.cells', []);
        $allUserCells = $this->trainingPlan->config->get("users.{$userId}.cells", []);

        $defaultOverrides = $allDefaultCells[$exerciseId] ?? $allDefaultCells[(string) $exerciseId] ?? [];
        $userOverrides = $allUserCells[$exerciseId] ?? $allUserCells[(string) $exerciseId] ?? [];
        $overrides = array_merge($defaultOverrides, $userOverrides);

        if (empty($overrides)) {
            return $block;
        }

        $weeks = $block->weeks;

        foreach ($overrides as $cellKey => $values) {
            if (! preg_match('/^w(\d+)-s(\d+)-set(\d+)$/', $cellKey, $matches)) {
                continue;
            }

            $weekIndex = (int) $matches[1];
            $sessionIndex = (int) $matches[2];
            $setIndex = (int) $matches[3];

            if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
                continue;
            }

            $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

            $newReps = $values['reps'] ?? $set->reps;
            $newWeight = $values['weight'] ?? $set->weight;

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = new TrainingSet(
                reps: $newReps,
                weight: $newWeight,
                oneRepMax: $set->oneRepMax,
            );
        }

        return $block->withWeeks($weeks);
    }

    protected function getWeekOverridesForExport(int $userId, int $exerciseId): array
    {
        $defaultOverrides = $this->trainingPlan->config->get("users.default.weeks.{$exerciseId}", []);
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.weeks.{$exerciseId}", []);

        $merged = [];
        $allKeys = array_unique(array_merge(array_keys($defaultOverrides), array_keys($userOverrides)));

        foreach ($allKeys as $weekKey) {
            $merged[$weekKey] = array_merge(
                $defaultOverrides[$weekKey] ?? [],
                $userOverrides[$weekKey] ?? []
            );
        }

        return $merged;
    }

    protected function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    }

    public function render()
    {
        return view('livewire.training.view.export');
    }
}
