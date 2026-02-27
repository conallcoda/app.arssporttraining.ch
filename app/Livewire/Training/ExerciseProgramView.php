<?php

namespace App\Livewire\Training;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Settings\DistanceSetting;
use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Settings\HeartRateZoneSetting;
use App\Data\Exercise\Settings\PaceSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\RestSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\TempoSetting;
use App\Data\Exercise\Settings\WattsSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramExercise;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Program')]
class ExerciseProgramView extends Component
{
    use InteractsWithFormData;

    public ExerciseProgram $exerciseProgram;

    public int $weeks = 5;

    public array $data = [];

    public function mount(ExerciseProgram $exerciseProgram): void
    {
        $this->exerciseProgram = $exerciseProgram;
        $this->loadExerciseData();
    }

    protected function loadExerciseData(): void
    {
        $this->exerciseProgram->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
        ]);

        $this->data = [
            'exercises' => $this->exerciseProgram->exercises->map(fn ($e) => [
                'id' => $e->id,
                '_key' => uniqid('item_', true),
                'sort' => $e->pivot->sort ?? 0,
            ])->values()->all(),
        ];
    }

    #[Computed]
    public function formConfig(): Form
    {
        return Form::make()
            ->fieldset('Exercises', [
                Exercises::make('exercises')->withOptions(),
            ]);
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[Computed]
    public function exercises(): Collection
    {
        return $this->exerciseProgram->exercises()
            ->orderByPivot('sort')
            ->get();
    }

    public function updatedDataExercises(): void
    {
        $hasCompleteExercises = collect($this->data['exercises'] ?? [])
            ->contains(fn ($item) => ! empty($item['id']));

        if ($hasCompleteExercises) {
            $this->saveExercises();
        }
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($this->data[$fieldName] as $i => $item) {
                $this->data[$fieldName][$i]['sort'] = $i;
            }
        }

        if ($fieldName === 'exercises') {
            $this->saveExercises();
        }
    }

    public function moveRelationshipItem(string $fieldName, int $index, int $direction): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $newIndex = $index + $direction;
        if ($newIndex < 0 || $newIndex >= count($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];
        [$items[$index], $items[$newIndex]] = [$items[$newIndex], $items[$index]];

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;

        if ($fieldName === 'exercises') {
            $this->saveExercises();
        }
    }

    public function saveExercises(): void
    {
        $currentExerciseIds = $this->exerciseProgram->exercises()->pluck('exercises.id')->toArray();
        $newExerciseIds = collect($this->data['exercises'] ?? [])
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToAdd = array_diff($newExerciseIds, $currentExerciseIds);
        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        ExerciseProgramExercise::where('exercise_program_id', $this->exerciseProgram->id)
            ->whereIn('exercise_id', $exercisesToRemove)
            ->delete();

        $config = $this->exerciseProgram->config;
        $configChanged = false;

        foreach ($exercisesToRemove as $exerciseId) {
            $config->removeExerciseOverrides($exerciseId);
            $configChanged = true;
        }

        foreach ($this->data['exercises'] as $index => $exerciseData) {
            if (empty($exerciseData['id'])) {
                continue;
            }

            $exerciseId = $exerciseData['id'];
            $sort = $exerciseData['sort'] ?? $index;

            if (in_array($exerciseId, $exercisesToAdd)) {
                ExerciseProgramExercise::create([
                    'exercise_program_id' => $this->exerciseProgram->id,
                    'exercise_id' => $exerciseId,
                    'sort' => $sort,
                ]);

                $exercise = Exercise::find($exerciseId);
                if ($exercise) {
                    $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
                    $config->setDefaultExerciseOverrides($exerciseId, $this->buildExerciseOverrides($configArray));
                    $configChanged = true;
                }
            } else {
                ExerciseProgramExercise::where('exercise_program_id', $this->exerciseProgram->id)
                    ->where('exercise_id', $exerciseId)
                    ->update(['sort' => $sort]);
            }
        }

        if ($configChanged) {
            $this->exerciseProgram->config = $config;
            $this->exerciseProgram->save();
        }

        $this->exerciseProgram->refresh();
        unset($this->exercises);
        $this->loadExerciseData();
    }

    protected function buildExerciseOverrides(array $configArray): ExerciseOverrides
    {
        return new ExerciseOverrides(
            settings: $configArray['settings'] ?? null,
            sets: isset($configArray['sets']) ? new SetsSetting(
                deload: $configArray['sets']['deload'] ?? 'none',
                deloadBy: $configArray['sets']['deloadBy'] ?? 1,
                label: $configArray['sets']['label'] ?? 'Set',
                default: $configArray['sets']['default'] ?? 4,
            ) : null,
            reps: isset($configArray['reps']) ? new RepsSetting(
                mode: $configArray['reps']['mode'] ?? 'manual',
                default: BilateralReps::parse($configArray['reps']['default'] ?? 10)->total(),
                stepDownInterval: $configArray['reps']['stepDownInterval'] ?? 2,
                decrement: $configArray['reps']['decrement'] ?? 2,
                minimum: $configArray['reps']['minimum'] ?? 1,
                label: $configArray['reps']['label'] ?? '',
                applyPer: $configArray['reps']['applyPer'] ?? 'session',
            ) : null,
            weight: isset($configArray['weight']) ? new WeightSetting(
                mode: $configArray['weight']['mode'] ?? 'manual',
                oneRepMaxModifier: $configArray['weight']['oneRepMaxModifier'] ?? 100,
                default: (float) ($configArray['weight']['default'] ?? 5),
                applyPer: $configArray['weight']['applyPer'] ?? 'session',
            ) : null,
            tempo: isset($configArray['tempo']) ? new TempoSetting(
                default: $configArray['tempo']['default'] ?? '3010',
                applyPer: $configArray['tempo']['applyPer'] ?? 'week',
            ) : null,
            rest: isset($configArray['rest']) ? new RestSetting(
                default: $configArray['rest']['default'] ?? 60,
                applyPer: $configArray['rest']['applyPer'] ?? 'week',
            ) : null,
            distance: isset($configArray['distance']) ? new DistanceSetting(
                unit: $configArray['distance']['unit'] ?? 'meters',
                default: $configArray['distance']['default'] ?? 500,
                applyPer: $configArray['distance']['applyPer'] ?? 'session',
            ) : null,
            duration: isset($configArray['duration']) ? new DurationSetting(
                unit: $configArray['duration']['unit'] ?? 'seconds',
                default: $configArray['duration']['default'] ?? 60,
                applyPer: $configArray['duration']['applyPer'] ?? 'session',
            ) : null,
            heartRate: isset($configArray['heartRate']) ? new HeartRateSetting(
                default: $configArray['heartRate']['default'] ?? '140',
                applyPer: $configArray['heartRate']['applyPer'] ?? 'session',
            ) : null,
            heartRateZone: isset($configArray['heartRateZone']) ? new HeartRateZoneSetting(
                default: $configArray['heartRateZone']['default'] ?? '3',
                applyPer: $configArray['heartRateZone']['applyPer'] ?? 'session',
            ) : null,
            pace: isset($configArray['pace']) ? new PaceSetting(
                default: $configArray['pace']['default'] ?? '5:00',
                applyPer: $configArray['pace']['applyPer'] ?? 'session',
            ) : null,
            watts: isset($configArray['watts']) ? new WattsSetting(
                default: $configArray['watts']['default'] ?? 100,
                applyPer: $configArray['watts']['applyPer'] ?? 'session',
            ) : null,
            gridOverrides: $configArray['overrides'] ?? ['cells' => [], 'weeks' => []],
        );
    }

    public function updateName(string $name): void
    {
        $this->exerciseProgram->name = $name;
        $this->exerciseProgram->save();
    }

    #[On('exercise-overrides-changed')]
    public function onExerciseOverridesChanged(): void
    {
        $this->exerciseProgram->refresh();
    }

    public function render()
    {
        return view('livewire.training.exercise-program-view');
    }
}
