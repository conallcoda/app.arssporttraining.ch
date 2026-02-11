<?php

namespace App\Console\Commands\Kilo;

use App\Data\Exercise\ExerciseType;

class ExerciseClassifier
{
    private const BARBELL_LIKE = [
        'BB', 'EZ Bar', 'Safety Bar', 'Trap Bar', 'Cambered Bar',
        'Thick Bar', 'Thick Angled Bar', 'Thick EZ Bar',
        'Thick Multi-Angled Bar', 'Thick Parallel Bar', 'Triceps Bar',
    ];

    private const LIGHT_EQUIPMENT = [
        'Band', 'Bands', 'Resistance Bands',
        'Swiss Ball', 'Foam Roller', 'Ab Wheel',
        'Med Ball', 'Rings', 'Rope',
    ];

    private const COMPOUND_CATEGORIES = ['Squat', 'Deadlift'];

    /**
     * @return array{type: string, confidence: string, reason: string}
     */
    public function classify(array $exercise): array
    {
        $bodyweight = $exercise['bodyweight'] ?? false;
        $equipment = $exercise['equipment'] ?? [];
        $categories = $exercise['categories'] ?? [];
        $bodyArea = $categories[0] ?? '';
        $category = $categories[1] ?? '';
        $subcategory = $categories[2] ?? null;

        $hasLightOnly = $this->hasOnlyLightEquipment($equipment);
        $isStabilization = $subcategory === 'Stabilization';

        if ($result = $this->classifyTimed($bodyweight, $equipment, $hasLightOnly, $isStabilization)) {
            return $result;
        }

        if ($result = $this->classifyConditioning($bodyweight, $equipment, $hasLightOnly, $isStabilization)) {
            return $result;
        }

        if ($result = $this->classifyStrengthAutomatic($bodyArea, $category, $equipment)) {
            return $result;
        }

        return $this->classifyStrengthManual($bodyweight, $equipment);
    }

    /**
     * @return array<int, array{exercise: array, classification: array{type: string, confidence: string, reason: string}}>
     */
    public function classifyAll(array $exercises): array
    {
        return array_map(fn (array $exercise) => [
            'exercise' => $exercise,
            'classification' => $this->classify($exercise),
        ], $exercises);
    }

    /**
     * @return array{type: string, confidence: string, reason: string}|null
     */
    private function classifyTimed(bool $bodyweight, array $equipment, bool $hasLightOnly, bool $isStabilization): ?array
    {
        if (! $isStabilization) {
            return null;
        }

        if ($bodyweight && empty($equipment)) {
            return $this->result(ExerciseType::Timed, 'high', 'Stabilization + bodyweight, no equipment');
        }

        if ($bodyweight && $hasLightOnly) {
            return $this->result(ExerciseType::Timed, 'medium', 'Stabilization + bodyweight, light equipment only');
        }

        if (! $bodyweight && $hasLightOnly) {
            return $this->result(ExerciseType::Timed, 'medium', 'Stabilization + light equipment only');
        }

        return null;
    }

    /**
     * @return array{type: string, confidence: string, reason: string}|null
     */
    private function classifyConditioning(bool $bodyweight, array $equipment, bool $hasLightOnly, bool $isStabilization): ?array
    {
        if ($isStabilization) {
            return null;
        }

        if ($bodyweight && empty($equipment)) {
            return $this->result(ExerciseType::Conditioning, 'high', 'Bodyweight, no equipment');
        }

        if ($bodyweight && $hasLightOnly) {
            return $this->result(ExerciseType::Conditioning, 'high', 'Bodyweight + light equipment only');
        }

        if (! $bodyweight && ! empty($equipment) && $hasLightOnly) {
            return $this->result(ExerciseType::Conditioning, 'medium', 'Light equipment only, not bodyweight');
        }

        return null;
    }

    /**
     * @return array{type: string, confidence: string, reason: string}|null
     */
    private function classifyStrengthAutomatic(string $bodyArea, string $category, array $equipment): ?array
    {
        if ($bodyArea !== 'Lower Body') {
            return null;
        }

        if (! in_array($category, self::COMPOUND_CATEGORIES)) {
            return null;
        }

        if (! $this->hasAnyEquipment($equipment, self::BARBELL_LIKE)) {
            return null;
        }

        return $this->result(ExerciseType::StrengthAutomatic, 'high', 'Lower Body + barbell-like + compound category');
    }

    /**
     * @return array{type: string, confidence: string, reason: string}
     */
    private function classifyStrengthManual(bool $bodyweight, array $equipment): array
    {
        if ($bodyweight && $this->hasAnyHeavyEquipment($equipment)) {
            return $this->result(ExerciseType::StrengthManual, 'low', 'Bodyweight + heavy equipment, needs review');
        }

        return $this->result(ExerciseType::StrengthManual, 'high', 'Weighted exercise');
    }

    /**
     * @return array{type: string, confidence: string, reason: string}
     */
    private function result(ExerciseType $type, string $confidence, string $reason): array
    {
        return [
            'type' => $type->value,
            'confidence' => $confidence,
            'reason' => $reason,
        ];
    }

    private function hasOnlyLightEquipment(array $equipment): bool
    {
        if (empty($equipment)) {
            return false;
        }

        foreach ($equipment as $item) {
            if (! in_array($item, self::LIGHT_EQUIPMENT)) {
                return false;
            }
        }

        return true;
    }

    private function hasAnyEquipment(array $equipment, array $list): bool
    {
        foreach ($equipment as $item) {
            if (in_array($item, $list)) {
                return true;
            }
        }

        return false;
    }

    private function hasAnyHeavyEquipment(array $equipment): bool
    {
        foreach ($equipment as $item) {
            if (! in_array($item, self::LIGHT_EQUIPMENT)) {
                return true;
            }
        }

        return false;
    }
}
