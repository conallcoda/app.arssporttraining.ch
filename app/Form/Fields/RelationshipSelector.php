<?php

namespace App\Form\Fields;

class RelationshipSelector extends \Coda\FormKit\Fields\RelationshipSelector
{
    public bool $clientModal = true;

    public bool $deferModalApply = true;

    /**
     * @return array{
     *   key: string|int|null,
     *   label: string,
     *   columns: array<int, array<string, mixed>>,
     *   views: array<string, array{columns: array<int, array<string, mixed>>}>
     * }
     */
    public function serializeRecordForClientModal(mixed $record): array
    {
        $serialized = parent::serializeRecordForClientModal($record);

        foreach (['selector_program_has_exercises', 'selector_program_exercise_count'] as $attribute) {
            $value = data_get($record, $attribute);

            if ($value !== null) {
                $serialized[$attribute] = $value;
            }
        }

        return $serialized;
    }
}
