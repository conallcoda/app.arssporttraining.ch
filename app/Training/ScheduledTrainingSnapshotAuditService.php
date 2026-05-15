<?php

namespace App\Training;

use App\Data\Training\Audit\ScheduledSnapshotAuditResultData;
use App\Data\Training\Audit\ScheduledSnapshotMismatchData;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSession;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;

class ScheduledTrainingSnapshotAuditService
{
    public function __construct(
        private readonly TrainingSessionCompiler $compiler,
        private readonly TrainingValueSnapshotCodec $valueCodec,
        private readonly ScheduledTrainingSnapshotClassifier $classifier,
    ) {}

    public function audit(TrainingProgramSlot $slot): ScheduledSnapshotAuditResultData
    {
        $slot->loadMissing([
            'trainingProgram.program.exercises',
            'exercises.sets.values',
        ]);

        $compiled = $this->compiler->compile($slot);
        $mismatches = [];

        $this->compareSessionMetadata($slot, $compiled, $mismatches);
        $this->compareExercises($slot, $compiled, $mismatches);

        return new ScheduledSnapshotAuditResultData(
            slotId: (int) $slot->id,
            classification: $this->classifier->classify($slot),
            matches: $mismatches === [],
            mismatchCount: count($mismatches),
            mismatches: $mismatches,
        );
    }

    /**
     * @param  array<int, ScheduledSnapshotMismatchData>  $mismatches
     */
    private function compareSessionMetadata(TrainingProgramSlot $slot, CompiledTrainingSession $compiled, array &$mismatches): void
    {
        $storedScheduledDate = ($slot->scheduled_date ?? $slot->datetime)?->format('Y-m-d');

        if ($storedScheduledDate !== $compiled->scheduledDate) {
            $mismatches[] = new ScheduledSnapshotMismatchData(
                path: 'slot.scheduled_date',
                kind: 'scheduled_date',
                expected: $compiled->scheduledDate,
                actual: $storedScheduledDate,
            );
        }

        if (($slot->compiled_version ?? null) !== $compiled->compiledVersion) {
            $mismatches[] = new ScheduledSnapshotMismatchData(
                path: 'slot.compiled_version',
                kind: 'compiled_version',
                expected: $compiled->compiledVersion,
                actual: $slot->compiled_version,
            );
        }
    }

    /**
     * @param  array<int, ScheduledSnapshotMismatchData>  $mismatches
     */
    private function compareExercises(TrainingProgramSlot $slot, CompiledTrainingSession $compiled, array &$mismatches): void
    {
        $storedExercises = $slot->exercises
            ->keyBy(fn (TrainingProgramSlotExercise $exercise): string => $this->exerciseSignature(
                exerciseId: (int) $exercise->exercise_id,
                sort: (int) $exercise->sort,
                group: $exercise->group,
                type: (string) ($exercise->type ?? 'main'),
            ));
        $compiledExercises = collect($compiled->exercises)
            ->keyBy(fn (CompiledTrainingExercise $exercise): string => $this->exerciseSignature(
                exerciseId: $exercise->exerciseId,
                sort: $exercise->sort,
                group: $exercise->group,
                type: $exercise->type,
            ));

        foreach ($compiledExercises as $signature => $compiledExercise) {
            $storedExercise = $storedExercises->get($signature);

            if (! $storedExercise instanceof TrainingProgramSlotExercise) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: 'exercise:'.$signature,
                    kind: 'missing_exercise',
                    expected: $signature,
                    actual: null,
                );

                continue;
            }

            $this->compareSets($signature, $storedExercise, $compiledExercise, $mismatches);
        }

        foreach ($storedExercises as $signature => $_storedExercise) {
            if (! $compiledExercises->has($signature)) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: 'exercise:'.$signature,
                    kind: 'extra_exercise',
                    expected: null,
                    actual: $signature,
                );
            }
        }
    }

    /**
     * @param  array<int, ScheduledSnapshotMismatchData>  $mismatches
     */
    private function compareSets(
        string $exerciseSignature,
        TrainingProgramSlotExercise $storedExercise,
        CompiledTrainingExercise $compiledExercise,
        array &$mismatches,
    ): void {
        $storedSets = $storedExercise->sets->keyBy('set_number');
        $compiledSets = collect($compiledExercise->sets)->keyBy('setNumber');

        foreach ($compiledSets as $setNumber => $compiledSet) {
            $storedSet = $storedSets->get($setNumber);

            if (! $storedSet instanceof TrainingProgramSlotSet) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: 'exercise:'.$exerciseSignature.'.set:'.$setNumber,
                    kind: 'missing_set',
                    expected: (int) $setNumber,
                    actual: null,
                );

                continue;
            }

            $this->compareValues($exerciseSignature, (int) $setNumber, $storedSet, $compiledSet, $mismatches);
        }

        foreach ($storedSets as $setNumber => $_storedSet) {
            if (! $compiledSets->has($setNumber)) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: 'exercise:'.$exerciseSignature.'.set:'.$setNumber,
                    kind: 'extra_set',
                    expected: null,
                    actual: (int) $setNumber,
                );
            }
        }
    }

    /**
     * @param  array<int, ScheduledSnapshotMismatchData>  $mismatches
     */
    private function compareValues(
        string $exerciseSignature,
        int $setNumber,
        TrainingProgramSlotSet $storedSet,
        CompiledTrainingSet $compiledSet,
        array &$mismatches,
    ): void {
        $storedValues = $storedSet->values->keyBy('setting_key');
        $compiledValues = collect($compiledSet->values)->keyBy('settingKey');

        foreach ($compiledValues as $settingKey => $compiledValue) {
            $storedValue = $storedValues->get($settingKey);
            $basePath = 'exercise:'.$exerciseSignature.'.set:'.$setNumber.'.value:'.$settingKey;

            if (! $storedValue instanceof TrainingProgramSlotSetValue) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: $basePath,
                    kind: 'missing_value',
                    expected: $settingKey,
                    actual: null,
                );

                continue;
            }

            $expectedSnapshot = $this->valueCodec->encodePlannedValue($compiledValue);

            $this->compareScalarField(
                $basePath.'.planned_value_type',
                'planned_value_type',
                $expectedSnapshot['planned_value_type'] ?? null,
                $storedValue->planned_value_type,
                $mismatches,
            );
            $this->compareScalarField(
                $basePath.'.planned_int_value',
                'planned_int_value',
                $this->normalizeComparableValue($expectedSnapshot['planned_int_value'] ?? null),
                $this->normalizeComparableValue($storedValue->planned_int_value),
                $mismatches,
            );
            $this->compareScalarField(
                $basePath.'.planned_decimal_value',
                'planned_decimal_value',
                $this->normalizeComparableValue($expectedSnapshot['planned_decimal_value'] ?? null),
                $this->normalizeComparableValue($storedValue->planned_decimal_value !== null ? (float) $storedValue->planned_decimal_value : null),
                $mismatches,
            );
            $this->compareScalarField(
                $basePath.'.planned_string_value',
                'planned_string_value',
                $this->normalizeComparableValue($expectedSnapshot['planned_string_value'] ?? null),
                $this->normalizeComparableValue($storedValue->planned_string_value),
                $mismatches,
            );
            $this->compareScalarField(
                $basePath.'.planned_json_value',
                'planned_json_value',
                $this->normalizeComparableValue($expectedSnapshot['planned_json_value'] ?? null),
                $this->normalizeComparableValue($storedValue->planned_json_value),
                $mismatches,
            );
            $this->compareScalarField(
                $basePath.'.unit',
                'unit',
                $expectedSnapshot['unit'] ?? null,
                $storedValue->unit,
                $mismatches,
            );
        }

        foreach ($storedValues as $settingKey => $_storedValue) {
            if (! $compiledValues->has($settingKey)) {
                $mismatches[] = new ScheduledSnapshotMismatchData(
                    path: 'exercise:'.$exerciseSignature.'.set:'.$setNumber.'.value:'.$settingKey,
                    kind: 'extra_value',
                    expected: null,
                    actual: $settingKey,
                );
            }
        }
    }

    /**
     * @param  array<int, ScheduledSnapshotMismatchData>  $mismatches
     */
    private function compareScalarField(
        string $path,
        string $kind,
        mixed $expected,
        mixed $actual,
        array &$mismatches,
    ): void {
        if ($expected === $actual) {
            return;
        }

        $mismatches[] = new ScheduledSnapshotMismatchData(
            path: $path,
            kind: $kind,
            expected: $expected,
            actual: $actual,
        );
    }

    private function normalizeComparableValue(mixed $value): mixed
    {
        if (is_float($value)) {
            return round($value, 3);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeComparableValue($item), $value);
        }

        return $value;
    }

    private function exerciseSignature(int $exerciseId, int $sort, ?string $group, string $type): string
    {
        return implode('|', [
            $exerciseId,
            $sort,
            $group ?? '',
            $type,
        ]);
    }
}
