<?php

namespace App\Training;

use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Training\TrainingProgram;

class TrainingProgramConfigOverrideNormalizer
{
    public function normalize(TrainingProgram $trainingProgram): bool
    {
        $exerciseProgram = $trainingProgram->program()->with('exercises')->first();

        if (! $exerciseProgram) {
            return false;
        }

        $pivotIds = $exerciseProgram->exercises
            ->pluck('pivot.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($pivotIds === []) {
            return false;
        }

        $pivotLookup = array_fill_keys($pivotIds, true);
        $config = $exerciseProgram->config;
        $userOverrides = $config->allUserExerciseOverrides();
        $changed = false;

        foreach ($userOverrides as $userId => $overridesByExercise) {
            foreach ($overridesByExercise as $programExerciseId => $overrides) {
                $programExerciseId = (int) $programExerciseId;

                if (isset($pivotLookup[$programExerciseId])) {
                    continue;
                }

                $candidateIds = [];

                foreach ($pivotIds as $candidateId) {
                    if (isset($overridesByExercise[$candidateId])) {
                        continue;
                    }

                    if (! $this->isCompatible(
                        $overrides,
                        $config->defaultExerciseOverrides($candidateId),
                    )) {
                        continue;
                    }

                    $candidateIds[] = $candidateId;
                }

                if (count($candidateIds) !== 1) {
                    continue;
                }

                $targetPivotId = $candidateIds[0];
                $config->setUserExerciseOverrides((int) $userId, $targetPivotId, ExerciseOverrides::from($overrides->toArray()));
                $config->removeUserExerciseOverrides((int) $userId, $programExerciseId);
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        $exerciseProgram->config = $config;
        $exerciseProgram->save();

        return true;
    }

    private function isCompatible(ExerciseOverrides $orphan, ExerciseOverrides $candidateDefault): bool
    {
        $anchors = 0;

        foreach (['reps', 'sets', 'heartRate', 'heartRateZone', 'pace', 'watts', 'distance', 'duration'] as $field) {
            $orphanValue = $orphan->{$field};

            if ($orphanValue === null) {
                continue;
            }

            $candidateValue = $candidateDefault->{$field};

            if ($candidateValue === null) {
                return false;
            }

            if ($this->normalizeValue($orphanValue) !== $this->normalizeValue($candidateValue)) {
                return false;
            }

            $anchors++;
        }

        return $anchors > 0;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return $value;
        }

        ksort($value);

        foreach ($value as $key => $child) {
            $value[$key] = $this->normalizeValue($child);
        }

        return $value;
    }
}
