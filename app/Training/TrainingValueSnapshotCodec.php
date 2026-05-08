<?php

namespace App\Training;

use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Models\Training\TrainingProgramSlotSetValue;

class TrainingValueSnapshotCodec
{
    /**
     * @return array<string, mixed>
     */
    public function encodePlannedValue(CompiledTrainingSetValue $value): array
    {
        $row = [
            'planned_value_type' => $value->plannedValueType,
            'planned_int_value' => null,
            'planned_decimal_value' => null,
            'planned_string_value' => null,
            'planned_json_value' => $value->plannedCanonicalValue,
            'unit' => $value->unit,
        ];

        if ($value->plannedValueType === null) {
            return $row;
        }

        return match ($value->plannedValueType) {
            'int' => array_merge($row, [
                'planned_int_value' => (int) $value->plannedValue,
            ]),
            'decimal' => array_merge($row, [
                'planned_decimal_value' => (float) $value->plannedValue,
            ]),
            'json' => array_merge($row, [
                'planned_json_value' => $value->plannedValue,
            ]),
            default => array_merge($row, [
                'planned_string_value' => (string) $value->plannedValue,
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function encodeActualValue(string $valueType, mixed $value, mixed $canonicalValue = null): array
    {
        $row = [
            'actual_value_type' => $valueType,
            'actual_int_value' => null,
            'actual_decimal_value' => null,
            'actual_string_value' => null,
            'actual_json_value' => $canonicalValue,
        ];

        return match ($valueType) {
            'int' => array_merge($row, ['actual_int_value' => (int) $value]),
            'decimal' => array_merge($row, ['actual_decimal_value' => round((float) $value, 3)]),
            'json' => array_merge($row, ['actual_json_value' => $value]),
            default => array_merge($row, ['actual_string_value' => (string) $value]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function clearActualValue(): array
    {
        return [
            'actual_value_type' => null,
            'actual_int_value' => null,
            'actual_decimal_value' => null,
            'actual_string_value' => null,
            'actual_json_value' => null,
            'actual_recorded_by' => null,
            'actual_recorded_at' => null,
            'actual_source' => null,
            'actual_is_explicit' => false,
        ];
    }

    public function extractPlannedValue(TrainingProgramSlotSetValue $valueRow): mixed
    {
        return $this->extractValue($valueRow, 'planned');
    }

    public function extractActualValue(TrainingProgramSlotSetValue $valueRow): mixed
    {
        return $this->extractValue($valueRow, 'actual');
    }

    public function extractActualType(TrainingProgramSlotSetValue $valueRow): ?string
    {
        return $valueRow->actual_value_type;
    }

    public function extractPlannedType(TrainingProgramSlotSetValue $valueRow): ?string
    {
        return $valueRow->planned_value_type;
    }

    private function extractValue(TrainingProgramSlotSetValue $valueRow, string $prefix): mixed
    {
        $type = $valueRow->{"{$prefix}_value_type"};

        return match ($type) {
            'int' => $valueRow->{"{$prefix}_int_value"},
            'decimal' => $valueRow->{"{$prefix}_decimal_value"} !== null ? (float) $valueRow->{"{$prefix}_decimal_value"} : null,
            'json' => $valueRow->{"{$prefix}_json_value"},
            default => $valueRow->{"{$prefix}_string_value"},
        };
    }
}
