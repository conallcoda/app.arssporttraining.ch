<?php

namespace App\Data\Training;

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
use App\Form\Fields\Training\Program\ProgramCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\Exercise;
use App\Models\ExercisePlanProgram;
use App\Models\ExercisePlanProgramExercise;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;

class ProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public ?string $plannable_type = null,
        public ?int $plannable_id = null,
        public string $name = '',
        public ?int $program_category_id = null,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public array $exercises = [],
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromExercisePlanProgram(ExercisePlanProgram $program): self
    {
        $program->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
            'programCategory',
        ]);

        $exercises = $program->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sort' => $exercise->pivot->sort ?? 0,
        ])->all();

        return new self(
            id: $program->id,
            plannable_type: $program->plannable_type,
            plannable_id: $program->plannable_id,
            name: $program->name ?? '',
            program_category_id: $program->program_category_id,
            categoryName: $program->programCategory?->name,
            categoryColor: $program->programCategory?->color,
            exercises: $exercises,
            updatedAt: $program->updated_at,
        );
    }

    public function persist(): void
    {
        $values = [
            'plannable_type' => $this->plannable_type,
            'plannable_id' => $this->plannable_id,
            'name' => $this->name,
            'program_category_id' => $this->program_category_id,
        ];

        if ($this->id === null) {
            $values['sort'] = (ExercisePlanProgram::where('plannable_type', $this->plannable_type)
                ->where('plannable_id', $this->plannable_id)->max('sort') ?? -1) + 1;
        }

        $program = ExercisePlanProgram::updateOrCreate(['id' => $this->id], $values);

        $this->id = $program->id;

        $this->syncExercisesWithDefaults($program);
    }

    protected function syncExercisesWithDefaults(ExercisePlanProgram $program): void
    {
        $currentExerciseIds = $program->exercises()->pluck('exercises.id')->toArray();
        $newExerciseIds = collect($this->exercises)
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToAdd = array_diff($newExerciseIds, $currentExerciseIds);
        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        ExercisePlanProgramExercise::where('exercise_plan_program_id', $program->id)
            ->whereIn('exercise_id', $exercisesToRemove)
            ->delete();

        /** @var Model $parent */
        $parent = $this->plannable_type::findOrFail($this->plannable_id);
        $config = $parent->config;
        $configChanged = false;

        foreach ($exercisesToRemove as $exerciseId) {
            $config->removeExerciseOverrides($exerciseId);
            $configChanged = true;
        }

        foreach ($this->exercises as $index => $exerciseData) {
            if (empty($exerciseData['id'])) {
                continue;
            }

            $exerciseId = $exerciseData['id'];
            $sort = $exerciseData['sort'] ?? $index;

            if (in_array($exerciseId, $exercisesToAdd)) {
                $exercise = Exercise::find($exerciseId);
                if ($exercise) {
                    ExercisePlanProgramExercise::create([
                        'exercise_plan_program_id' => $program->id,
                        'exercise_id' => $exerciseId,
                        'sort' => $sort,
                    ]);

                    $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
                    $config->setDefaultExerciseOverrides($exerciseId, new ExerciseOverrides(
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
                    ));
                    $configChanged = true;
                }
            } else {
                ExercisePlanProgramExercise::where('exercise_plan_program_id', $program->id)
                    ->where('exercise_id', $exerciseId)
                    ->update(['sort' => $sort]);
            }
        }

        if ($configChanged) {
            $parent->config = $config;
            $parent->save();
        }
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                ProgramName::make('name'),
                ProgramCategory::make('program_category_id')->withOptions(),
                Exercises::make('exercises')->withOptions(),
            ]);
    }
}
