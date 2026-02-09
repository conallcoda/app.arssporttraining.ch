<?php

namespace App\Cms\Data;

abstract class AbstractConfig extends AbstractData
{
    public static function getFields(array $data = []): array
    {
        return [];
    }

    protected function formatBadgeValue(string $field, mixed $value): ?string
    {
        return null;
    }

    /** @return string[] */
    public function badgeFields(): array
    {
        return [];
    }

    public function toBadges(): array
    {
        $badges = [];
        $allowedFields = $this->badgeFields();
        $fieldsMap = collect(static::getFields($this->toArray()))->keyBy('name');

        foreach ($allowedFields as $fieldName) {
            $field = $fieldsMap->get($fieldName);
            $value = $this->{$fieldName} ?? null;

            if ($value === null || $value === '' || $value === 0 || $value === 0.0 || $value === '00:00') {
                continue;
            }

            $displayValue = $this->formatBadgeValue($fieldName, $value);

            if ($displayValue === null) {
                $displayValue = (string) $value;

                if ($field && isset($field->suffix) && $field->suffix) {
                    $displayValue .= ' '.$field->suffix;
                }
            }

            $badges[] = [
                'label' => $displayValue,
                'modalField' => $fieldName,
            ];
        }

        return $badges;
    }
}
