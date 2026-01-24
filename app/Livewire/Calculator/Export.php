<?php

namespace App\Livewire\Calculator;

use App\Exports\AthleteTrainingPlanExport;
use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseBlockHistory;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\ExerciseData;
use App\Models\Training\ExercisePlan\GroupTrainingPlan;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class Export extends Component
{
    #[Reactive]
    public array $athletes = [];

    #[Reactive]
    public array $exercises = [];

    #[Reactive]
    public ?AthleteExerciseConfig $config = null;

    public array $selectedAthleteIds = [];

    public bool $exporting = false;

    public function mount(): void
    {
        if (! empty($this->athletes)) {
            $this->selectedAthleteIds = [$this->athletes[0]->id];
        }
    }

    public function updatedAthletes(): void
    {
        $currentIds = array_map(fn ($a) => $a->id, $this->athletes);
        $this->selectedAthleteIds = array_intersect($this->selectedAthleteIds, $currentIds);

        if (empty($this->selectedAthleteIds) && ! empty($currentIds)) {
            $this->selectedAthleteIds = [$currentIds[0]];
        }
    }

    #[Computed]
    public function previewAthlete(): ?AthleteData
    {
        if (empty($this->selectedAthleteIds) || empty($this->athletes)) {
            return null;
        }

        $firstSelectedId = $this->selectedAthleteIds[0];

        return collect($this->athletes)->first(fn ($a) => $a->id === $firstSelectedId);
    }

    #[Computed]
    public function previewPlans(): array
    {
        $athlete = $this->previewAthlete;
        if (! $athlete || empty($this->exercises) || ! $this->config) {
            return [];
        }

        $groupedBlocks = $this->getGroupedBlocksForAthlete($athlete);
        $plans = [];

        foreach ($groupedBlocks as $groupName => $exercises) {
            $plans[] = new GroupTrainingPlan($athlete, $groupName, $exercises);
        }

        return $plans;
    }

    public function selectAll(): void
    {
        $this->selectedAthleteIds = array_map(fn ($a) => $a->id, $this->athletes);
    }

    public function deselectAll(): void
    {
        $this->selectedAthleteIds = [];
    }

    public function export(): StreamedResponse|BinaryFileResponse
    {
        $this->exporting = true;

        $selectedAthletes = array_filter(
            $this->athletes,
            fn ($a) => in_array($a->id, $this->selectedAthleteIds)
        );

        if (empty($selectedAthletes) || empty($this->exercises) || ! $this->config) {
            $this->exporting = false;

            return response()->streamDownload(function () {
                echo '';
            }, 'empty.zip');
        }

        $selectedAthletes = array_values($selectedAthletes);

        if (count($selectedAthletes) === 1) {
            $athlete = $selectedAthletes[0];
            $groupedBlocks = $this->getGroupedBlocksForAthlete($athlete);
            $filename = $this->sanitizeFilename($athlete->name).'_training_plan.xlsx';

            $this->exporting = false;

            return Excel::download(
                new AthleteTrainingPlanExport($athlete, $groupedBlocks),
                $filename
            );
        }

        $uniqueId = uniqid();
        $tempDir = 'temp/exports/'.$uniqueId;
        Storage::disk('local')->makeDirectory($tempDir);

        $files = [];

        foreach ($selectedAthletes as $athlete) {
            $groupedBlocks = $this->getGroupedBlocksForAthlete($athlete);
            $filename = $this->sanitizeFilename($athlete->name).'_training_plan.xlsx';

            Excel::store(
                new AthleteTrainingPlanExport($athlete, $groupedBlocks),
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

    protected function getGroupedBlocksForAthlete(AthleteData $athlete): array
    {
        $grouped = [];

        foreach ($this->exercises as $exercise) {
            $exerciseConfig = $this->buildExerciseConfig($athlete, $exercise);
            $history = AthleteExerciseBlockHistory::example($exerciseConfig);

            $group = $exercise->group;
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = [
                'exercise' => $exercise,
                'block' => $history->current(),
            ];
        }

        return $grouped;
    }

    protected function buildExerciseConfig(AthleteData $athlete, ExerciseData $exercise): AthleteExerciseConfig
    {
        return AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $athlete,
            exercise: $exercise,
            target: $this->config->target,
            strategy: $this->config->strategy,
            strategyConfig: $this->config->strategyConfig,
            initialRulesConfig: $this->config->initialRulesConfig,
            actionRulesConfig: $this->config->actionRulesConfig,
        );
    }

    protected function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    }

    public function render()
    {
        return view('livewire.calculator.export');
    }
}
