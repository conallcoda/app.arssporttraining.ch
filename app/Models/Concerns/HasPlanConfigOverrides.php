<?php

namespace App\Models\Concerns;

use App\Models\Exercise\ExercisePlanConfigOverride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait HasPlanConfigOverrides
{
    /** @var array<int, array<string, mixed>>|null */
    protected ?array $pendingPlanConfigOverrideRows = null;

    public static function bootHasPlanConfigOverrides(): void
    {
        static::deleting(function ($model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->planConfigOverrides()->delete();
        });
    }

    public function save(array $options = []): bool
    {
        $result = parent::save($options);

        if ($result) {
            $this->flushPendingPlanConfigOverrideRows();
        }

        return $result;
    }

    public function planConfigOverrides(): MorphMany
    {
        return $this->morphMany(ExercisePlanConfigOverride::class, 'owner');
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    public function stashPendingPlanConfigOverrideRows(?array $rows): void
    {
        $this->pendingPlanConfigOverrideRows = $rows;
    }

    public function hasPendingPlanConfigOverrideRows(): bool
    {
        return $this->pendingPlanConfigOverrideRows !== null;
    }

    public function flushPendingPlanConfigOverrideRows(): void
    {
        if ($this->pendingPlanConfigOverrideRows === null) {
            return;
        }

        $rows = $this->pendingPlanConfigOverrideRows;
        $this->pendingPlanConfigOverrideRows = null;

        $this->syncPlanConfigOverrideRows($rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function syncPlanConfigOverrideRows(array $rows): void
    {
        $existing = $this->planConfigOverrides()->get()->keyBy(
            fn (ExercisePlanConfigOverride $override): string => $this->planConfigOverrideKey([
                'programExerciseId' => $override->program_exercise_id,
                'userId' => $override->user_id,
                'scope' => $override->scope,
                'target' => $override->target,
                'week' => $override->week_index,
                'session' => $override->session_index,
                'set' => $override->set_index,
                'settingKey' => $override->setting_key,
            ]),
        );

        $desired = collect($rows)
            ->filter(fn (array $row): bool => (int) ($row['programExerciseId'] ?? 0) > 0
                && (string) ($row['settingKey'] ?? '') !== '')
            ->keyBy(fn (array $row): string => $this->planConfigOverrideKey($row));

        foreach ($existing as $key => $override) {
            if (! $desired->has($key)) {
                $override->delete();
            }
        }

        foreach ($desired as $key => $row) {
            $encodedValue = json_encode($row['value'] ?? null);

            if (isset($existing[$key])) {
                $override = $existing[$key];

                if (($override->attributes['value'] ?? null) === $encodedValue) {
                    continue;
                }

                $override->forceFill(['value' => $encodedValue])->save();

                continue;
            }

            $this->planConfigOverrides()->create([
                'program_exercise_id' => (int) $row['programExerciseId'],
                'user_id' => array_key_exists('userId', $row) && $row['userId'] !== null
                    ? (int) $row['userId']
                    : null,
                'scope' => (string) ($row['scope'] ?? 'current'),
                'target' => (string) ($row['target'] ?? 'cell'),
                'week_index' => (int) ($row['week'] ?? 0),
                'session_index' => (int) ($row['session'] ?? 0),
                'set_index' => array_key_exists('set', $row) && $row['set'] !== null
                    ? (int) $row['set']
                    : null,
                'setting_key' => (string) $row['settingKey'],
                'value' => $encodedValue,
            ]);
        }
    }

    private function planConfigOverrideKey(array $row): string
    {
        $userId = $row['userId'] ?? null;
        $set = $row['set'] ?? null;

        return implode('|', [
            (int) ($row['programExerciseId'] ?? 0),
            $userId !== null ? (int) $userId : 'null',
            (string) ($row['scope'] ?? 'current'),
            (string) ($row['target'] ?? 'cell'),
            (int) ($row['week'] ?? 0),
            (int) ($row['session'] ?? 0),
            $set !== null && $set !== '' ? (int) $set : 'null',
            (string) ($row['settingKey'] ?? ''),
        ]);
    }
}
