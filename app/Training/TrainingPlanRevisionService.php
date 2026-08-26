<?php

namespace App\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\UserTypeEnum;
use App\Support\Training\ApplyPerScope;
use Illuminate\Database\Eloquent\Model;

class TrainingPlanRevisionService
{
    private const TRACKED_OVERRIDE_FIELDS = [
        'settings',
        'startsAtDate',
        'sets',
        'reps',
        'weight',
        'tempo',
        'rest',
        'distance',
        'duration',
        'heartRate',
        'heartRateZone',
        'pace',
        'watts',
        'sessionGrouping',
        'disabled',
    ];

    /**
     * @param  array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>}  $before
     * @param  array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>}  $after
     * @param  array<string, array<string, mixed>>  $fieldConfigMap
     */
    public function recordGridOverrideChanges(
        Model $owner,
        int $programExerciseId,
        ?int $userId,
        array $before,
        array $after,
        array $fieldConfigMap = [],
        string $action = 'save_grid_overrides',
        ?string $source = null,
    ): ?TrainingRevisionBatch {
        $beforeRows = $this->flattenGridOverrides($before);
        $afterRows = $this->flattenGridOverrides($after);
        $keys = array_values(array_unique(array_merge(array_keys($beforeRows), array_keys($afterRows))));
        $changes = [];

        foreach ($keys as $key) {
            $beforeRow = $beforeRows[$key] ?? null;
            $afterRow = $afterRows[$key] ?? null;

            if (($beforeRow['value'] ?? null) === ($afterRow['value'] ?? null)) {
                continue;
            }

            $changes[] = [
                'before' => $beforeRow,
                'after' => $afterRow,
            ];
        }

        if ($changes === []) {
            return null;
        }

        $batch = TrainingRevisionBatch::create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'domain' => 'plan',
            'action' => $action,
            'changed_by' => auth()->id(),
            'source' => $source ?? $this->resolvePlanSource(),
            'reason' => app(TrainingAuditContext::class)->reason([
                'program_exercise_id' => $programExerciseId,
                'user_id' => $userId,
            ]),
        ]);

        foreach ($changes as $change) {
            $beforeRow = $change['before'];
            $afterRow = $change['after'];
            $field = $afterRow['field'] ?? $beforeRow['field'];

            if (! is_string($field) || $field === '') {
                continue;
            }

            $fieldConfig = $fieldConfigMap[$field] ?? [];
            $beforeEncoded = $this->encodePlanValue($field, $beforeRow['value'] ?? null, $fieldConfig);
            $afterEncoded = $this->encodePlanValue($field, $afterRow['value'] ?? null, $fieldConfig);

            TrainingPlanValueRevision::create([
                'batch_id' => $batch->id,
                'owner_type' => $owner::class,
                'owner_id' => $owner->getKey(),
                'program_exercise_id' => $programExerciseId,
                'user_id' => $userId,
                'setting_key' => $field,
                'week_index' => (int) ($afterRow['week'] ?? $beforeRow['week'] ?? 0),
                'session_index' => (int) ($afterRow['session'] ?? $beforeRow['session'] ?? 0),
                'set_index' => array_key_exists('set', $afterRow ?? []) ? $afterRow['set'] : ($beforeRow['set'] ?? null),
                'changed_by' => auth()->id(),
                'source' => $source ?? $this->resolvePlanSource(),
                'before_value_type' => $beforeEncoded['value_type'],
                'before_int_value' => $beforeEncoded['int_value'],
                'before_decimal_value' => $beforeEncoded['decimal_value'],
                'before_string_value' => $beforeEncoded['string_value'],
                'before_json_value' => $beforeEncoded['json_value'],
                'after_value_type' => $afterEncoded['value_type'],
                'after_int_value' => $afterEncoded['int_value'],
                'after_decimal_value' => $afterEncoded['decimal_value'],
                'after_string_value' => $afterEncoded['string_value'],
                'after_json_value' => $afterEncoded['json_value'],
                'unit' => $this->resolveUnit($field, $fieldConfig),
            ]);
        }

        return $batch;
    }

    public function recordOverrideSettingChanges(
        Model $owner,
        int $programExerciseId,
        ?int $userId,
        ExerciseOverrides $before,
        ExerciseOverrides $after,
        string $action = 'update_setting_overrides',
    ): ?TrainingRevisionBatch {
        $changes = [];

        foreach (self::TRACKED_OVERRIDE_FIELDS as $field) {
            $beforeValue = $this->normalizeOverrideFieldValue($before->{$field} ?? null);
            $afterValue = $this->normalizeOverrideFieldValue($after->{$field} ?? null);
            $changes = array_merge(
                $changes,
                $this->flattenOverrideSettingChanges($field, $beforeValue, $afterValue),
            );
        }

        if ($changes === []) {
            return null;
        }

        $batch = TrainingRevisionBatch::create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'domain' => 'plan',
            'action' => $action,
            'changed_by' => auth()->id(),
            'source' => $this->resolvePlanSource(),
            'reason' => app(TrainingAuditContext::class)->reason([
                'program_exercise_id' => $programExerciseId,
                'user_id' => $userId,
            ]),
        ]);

        foreach ($changes as $change) {
            $rootField = $this->rootSettingKey($change['field']);
            $beforeEncoded = $this->encodeOverrideSettingValue(
                rootField: $rootField,
                path: $change['field'],
                value: $change['before'],
            );
            $afterEncoded = $this->encodeOverrideSettingValue(
                rootField: $rootField,
                path: $change['field'],
                value: $change['after'],
            );

            TrainingPlanValueRevision::create([
                'batch_id' => $batch->id,
                'owner_type' => $owner::class,
                'owner_id' => $owner->getKey(),
                'program_exercise_id' => $programExerciseId,
                'user_id' => $userId,
                'setting_key' => $change['field'],
                'week_index' => 0,
                'session_index' => 0,
                'set_index' => null,
                'changed_by' => auth()->id(),
                'source' => $this->resolvePlanSource(),
                'before_value_type' => $beforeEncoded['value_type'],
                'before_int_value' => $beforeEncoded['int_value'],
                'before_decimal_value' => $beforeEncoded['decimal_value'],
                'before_string_value' => $beforeEncoded['string_value'],
                'before_json_value' => $beforeEncoded['json_value'],
                'after_value_type' => $afterEncoded['value_type'],
                'after_int_value' => $afterEncoded['int_value'],
                'after_decimal_value' => $afterEncoded['decimal_value'],
                'after_string_value' => $afterEncoded['string_value'],
                'after_json_value' => $afterEncoded['json_value'],
                'unit' => $this->resolveUnit($change['field'], []),
            ]);
        }

        return $batch;
    }

    /**
     * @param  array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>}  $gridOverrides
     * @return array<string, array{field: string, week: int, session: int, set?: int|null, value: mixed}>
     */
    private function flattenGridOverrides(array $gridOverrides): array
    {
        $rows = [];

        foreach ($gridOverrides['sessions'] ?? [] as $sessionOverride) {
            foreach (($sessionOverride['data'] ?? []) as $field => $value) {
                $rows[$this->gridKey(
                    field: (string) $field,
                    week: (int) ($sessionOverride['week'] ?? 0),
                    session: (int) ($sessionOverride['session'] ?? 0),
                    set: null,
                )] = [
                    'field' => (string) $field,
                    'week' => (int) ($sessionOverride['week'] ?? 0),
                    'session' => (int) ($sessionOverride['session'] ?? 0),
                    'set' => null,
                    'value' => $value,
                ];
            }
        }

        foreach ($gridOverrides['cells'] ?? [] as $cellOverride) {
            foreach (($cellOverride['data'] ?? []) as $field => $value) {
                $rows[$this->gridKey(
                    field: (string) $field,
                    week: (int) ($cellOverride['week'] ?? 0),
                    session: (int) ($cellOverride['session'] ?? 0),
                    set: (int) ($cellOverride['set'] ?? 0),
                )] = [
                    'field' => (string) $field,
                    'week' => (int) ($cellOverride['week'] ?? 0),
                    'session' => (int) ($cellOverride['session'] ?? 0),
                    'set' => (int) ($cellOverride['set'] ?? 0),
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }

    private function gridKey(string $field, int $week, int $session, ?int $set): string
    {
        return implode(':', [$field, $week, $session, $set === null ? 'session' : $set]);
    }

    /**
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function flattenOverrideSettingChanges(string $field, mixed $before, mixed $after): array
    {
        if ($before === $after) {
            return [];
        }

        $beforeIsAssoc = $this->isAssociativeArray($before);
        $afterIsAssoc = $this->isAssociativeArray($after);

        if ($beforeIsAssoc || $afterIsAssoc) {
            $changes = [];
            $beforeArray = is_array($before) ? $before : [];
            $afterArray = is_array($after) ? $after : [];
            $keys = array_values(array_unique(array_merge(array_keys($beforeArray), array_keys($afterArray))));

            foreach ($keys as $key) {
                if (! is_string($key) && ! is_int($key)) {
                    continue;
                }

                $changes = array_merge(
                    $changes,
                    $this->flattenOverrideSettingChanges(
                        $field.'.'.$key,
                        $beforeArray[$key] ?? null,
                        $afterArray[$key] ?? null,
                    ),
                );
            }

            return $changes;
        }

        return [[
            'field' => $field,
            'before' => $before,
            'after' => $after,
        ]];
    }

    private function normalizeOverrideFieldValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        return ApplyPerScope::normalizeConfigForComparison($value);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{value_type: ?string, int_value: ?int, decimal_value: ?float, string_value: ?string, json_value: ?array}
     */
    private function encodePlanValue(string $field, mixed $value, array $config): array
    {
        if ($value === null) {
            return [
                'value_type' => null,
                'int_value' => null,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => null,
            ];
        }

        $normalizedValue = $this->normalizeStoredPlanValue($field, $value, $config);
        $valueType = $this->resolveValueType($field, $normalizedValue, $config);

        return match ($valueType) {
            'int' => [
                'value_type' => 'int',
                'int_value' => (int) $normalizedValue,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => null,
            ],
            'decimal' => [
                'value_type' => 'decimal',
                'int_value' => null,
                'decimal_value' => round((float) $normalizedValue, 3),
                'string_value' => null,
                'json_value' => null,
            ],
            'json' => [
                'value_type' => 'json',
                'int_value' => null,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => is_array($normalizedValue) ? $normalizedValue : null,
            ],
            default => [
                'value_type' => 'string',
                'int_value' => null,
                'decimal_value' => null,
                'string_value' => is_string($normalizedValue) ? $normalizedValue : (string) $normalizedValue,
                'json_value' => null,
            ],
        };
    }

    /**
     * @return array{value_type: ?string, int_value: ?int, decimal_value: ?float, string_value: ?string, json_value: ?array}
     */
    private function encodeOverrideSettingValue(string $rootField, string $path, mixed $value): array
    {
        if ($value === null) {
            return [
                'value_type' => null,
                'int_value' => null,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => null,
            ];
        }

        if (is_array($value)) {
            return [
                'value_type' => 'json',
                'int_value' => null,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => $value,
            ];
        }

        if ($this->leafSettingKey($path) === 'default') {
            $value = $this->normalizeStoredPlanValue($rootField, $value, []);
        }

        if (is_bool($value)) {
            return [
                'value_type' => 'int',
                'int_value' => $value ? 1 : 0,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => null,
            ];
        }

        if (is_float($value)) {
            return [
                'value_type' => 'decimal',
                'int_value' => null,
                'decimal_value' => round($value, 3),
                'string_value' => null,
                'json_value' => null,
            ];
        }

        if (is_int($value)) {
            return [
                'value_type' => 'int',
                'int_value' => $value,
                'decimal_value' => null,
                'string_value' => null,
                'json_value' => null,
            ];
        }

        return [
            'value_type' => 'string',
            'int_value' => null,
            'decimal_value' => null,
            'string_value' => is_string($value) ? trim($value) : (string) $value,
            'json_value' => null,
        ];
    }

    private function rootSettingKey(string $path): string
    {
        return (string) explode('.', $path)[0];
    }

    private function leafSettingKey(string $path): string
    {
        $parts = explode('.', $path);

        return (string) end($parts);
    }

    private function isAssociativeArray(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    private function normalizeStoredPlanValue(string $field, mixed $value, array $config): mixed
    {
        if (is_array($value) || $value === null) {
            return $value;
        }

        $enum = ExerciseSetting::tryFrom($field);
        $settingClass = $enum?->settingClass();

        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return is_string($value) ? trim($value) : $value;
        }

        $normalized = is_string($value) ? trim($value) : $value;

        if ($normalized === '') {
            return $normalized;
        }

        if (in_array($field, ['duration', 'reps', 'weight'], true)) {
            $normalized = $settingClass::normalizeAthleteValue($normalized, $config);
        }

        $canonical = $settingClass::athleteCanonicalValue($normalized, $config);

        if (($canonical['format'] ?? null) !== 'scalar' || ! is_numeric((string) $normalized)) {
            return $normalized;
        }

        return str_contains((string) $normalized, '.')
            ? round((float) $normalized, 3)
            : (int) $normalized;
    }

    private function resolveValueType(string $field, mixed $value, array $config): string
    {
        if (is_array($value)) {
            return 'json';
        }

        $enum = ExerciseSetting::tryFrom($field);
        $settingClass = $enum?->settingClass();
        $inputMeta = is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)
            ? $settingClass::inputMeta($config)
            : new CellInputMeta;

        if (($inputMeta->inputType ?? 'text') === 'text') {
            if ($field === 'duration' && is_numeric($value)) {
                return 'int';
            }

            if ((is_int($value) || is_float($value)) && is_numeric((string) $value)) {
                return is_float($value) ? 'decimal' : 'int';
            }

            return 'string';
        }

        if (is_float($value)) {
            return 'decimal';
        }

        if (is_string($value) && str_contains($value, '.')) {
            return is_numeric($value) ? 'decimal' : 'string';
        }

        if (is_numeric($value)) {
            return 'int';
        }

        return 'string';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveUnit(string $field, array $config): ?string
    {
        $enum = ExerciseSetting::tryFrom($field);
        $settingClass = $enum?->settingClass();

        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return null;
        }

        return $settingClass::resolveUnitLabel($config);
    }

    private function resolvePlanSource(): string
    {
        $type = auth()->user()?->type;

        return match ($type) {
            UserTypeEnum::Coach => 'coach',
            UserTypeEnum::Admin => 'admin',
            default => 'system',
        };
    }
}
