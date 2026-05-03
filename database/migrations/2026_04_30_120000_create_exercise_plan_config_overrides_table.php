<?php

use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Support\Training\GridOverrideNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_plan_config_overrides', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->foreignId('program_exercise_id')
                ->constrained('exercise_program_exercises')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('scope', 16)->default('current');
            $table->string('target', 16);
            $table->unsignedInteger('week_index');
            $table->unsignedInteger('session_index');
            $table->unsignedInteger('set_index')->nullable();
            $table->string('setting_key', 64);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->index(
                ['owner_type', 'owner_id', 'program_exercise_id', 'user_id'],
                'epco_owner_pivot_user_idx',
            );
            $table->index(
                ['program_exercise_id', 'user_id'],
                'epco_pivot_user_idx',
            );
        });

        $this->backfillFrom('exercise_programs', ExerciseProgram::class);
        $this->backfillFrom('exercise_plans', ExercisePlan::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_plan_config_overrides');
    }

    private function backfillFrom(string $table, string $ownerClass): void
    {
        DB::table($table)
            ->select('id', 'config')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $ownerClass): void {
                foreach ($rows as $row) {
                    if ($row->config === null) {
                        continue;
                    }

                    $config = json_decode($row->config, true);

                    if (! is_array($config)) {
                        continue;
                    }

                    $insertRows = $this->collectFlatRows($config);

                    if ($insertRows !== []) {
                        $pivotIds = array_unique(array_map(static fn (array $r): int => (int) $r['programExerciseId'], $insertRows));
                        $existingPivotIds = DB::table('exercise_program_exercises')
                            ->whereIn('id', $pivotIds)
                            ->pluck('id')
                            ->map(static fn ($id): int => (int) $id)
                            ->all();

                        $existingPivotIds = array_flip($existingPivotIds);
                        $now = now();

                        foreach ($insertRows as $insertRow) {
                            if (! isset($existingPivotIds[(int) $insertRow['programExerciseId']])) {
                                continue;
                            }

                            DB::table('exercise_plan_config_overrides')->insert([
                                'owner_type' => $ownerClass,
                                'owner_id' => $row->id,
                                'program_exercise_id' => $insertRow['programExerciseId'],
                                'user_id' => $insertRow['userId'],
                                'scope' => $insertRow['scope'],
                                'target' => $insertRow['target'],
                                'week_index' => $insertRow['week'],
                                'session_index' => $insertRow['session'],
                                'set_index' => $insertRow['set'],
                                'setting_key' => $insertRow['settingKey'],
                                'value' => json_encode($insertRow['value']),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }

                    $stripped = $this->stripOverrideShapesFromConfig($config);

                    if ($stripped !== $config) {
                        DB::table($table)->where('id', $row->id)->update([
                            'config' => json_encode($stripped),
                        ]);
                    }
                }
            });
    }

    /** @return array<int, array<string, mixed>> */
    private function collectFlatRows(array $config): array
    {
        $rows = [];

        foreach ($config['overrideValues'] ?? [] as $value) {
            if (! is_array($value)) {
                continue;
            }

            $programExerciseId = (int) ($value['programExerciseId'] ?? 0);

            if ($programExerciseId <= 0) {
                continue;
            }

            $settingKey = (string) ($value['settingKey'] ?? '');

            if ($settingKey === '') {
                continue;
            }

            $rows[] = [
                'programExerciseId' => $programExerciseId,
                'userId' => array_key_exists('userId', $value) && $value['userId'] !== null
                    ? (int) $value['userId']
                    : null,
                'scope' => (string) ($value['scope'] ?? 'current'),
                'target' => (string) ($value['target'] ?? 'cell'),
                'week' => (int) ($value['week'] ?? 0),
                'session' => (int) ($value['session'] ?? 0),
                'set' => array_key_exists('set', $value) && $value['set'] !== null
                    ? (int) $value['set']
                    : null,
                'settingKey' => $settingKey,
                'value' => $value['value'] ?? null,
            ];
        }

        foreach ($config['exercises'] ?? [] as $programExerciseId => $overrides) {
            array_push(
                $rows,
                ...$this->flattenLegacyExerciseOverrides((int) $programExerciseId, null, is_array($overrides) ? $overrides : []),
            );
        }

        foreach ($config['userExercises'] ?? [] as $userId => $byPivot) {
            if (! is_array($byPivot)) {
                continue;
            }

            foreach ($byPivot as $programExerciseId => $overrides) {
                array_push(
                    $rows,
                    ...$this->flattenLegacyExerciseOverrides((int) $programExerciseId, (int) $userId, is_array($overrides) ? $overrides : []),
                );
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function flattenLegacyExerciseOverrides(int $programExerciseId, ?int $userId, array $overrides): array
    {
        $current = GridOverrideNormalizer::normalize($overrides['gridOverrides'] ?? null);
        $historical = GridOverrideNormalizer::normalize($overrides['historicalGridOverrides'] ?? null);
        $baseline = GridOverrideNormalizer::normalize($overrides['baselineGridOverrides'] ?? null);

        return array_merge(
            $this->flattenLegacyGridOverrides($programExerciseId, $userId, 'current', $current),
            $this->flattenLegacyGridOverrides($programExerciseId, $userId, 'historical', $historical),
            $this->flattenLegacyGridOverrides($programExerciseId, $userId, 'baseline', $baseline),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function flattenLegacyGridOverrides(int $programExerciseId, ?int $userId, string $scope, array $gridOverrides): array
    {
        $rows = [];

        foreach ($gridOverrides['sessions'] ?? [] as $entry) {
            foreach (($entry['data'] ?? []) as $settingKey => $value) {
                $rows[] = [
                    'programExerciseId' => $programExerciseId,
                    'userId' => $userId,
                    'scope' => $scope,
                    'target' => 'session',
                    'week' => (int) ($entry['week'] ?? 0),
                    'session' => (int) ($entry['session'] ?? 0),
                    'set' => null,
                    'settingKey' => (string) $settingKey,
                    'value' => $value,
                ];
            }
        }

        foreach ($gridOverrides['cells'] ?? [] as $entry) {
            foreach (($entry['data'] ?? []) as $settingKey => $value) {
                $rows[] = [
                    'programExerciseId' => $programExerciseId,
                    'userId' => $userId,
                    'scope' => $scope,
                    'target' => 'cell',
                    'week' => (int) ($entry['week'] ?? 0),
                    'session' => (int) ($entry['session'] ?? 0),
                    'set' => (int) ($entry['set'] ?? 0),
                    'settingKey' => (string) $settingKey,
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }

    private function stripOverrideShapesFromConfig(array $config): array
    {
        unset($config['overrideValues']);

        if (isset($config['exercises']) && is_array($config['exercises'])) {
            foreach ($config['exercises'] as $programExerciseId => $overrides) {
                if (! is_array($overrides)) {
                    continue;
                }

                unset(
                    $overrides['gridOverrides'],
                    $overrides['historicalGridOverrides'],
                    $overrides['baselineGridOverrides'],
                );

                $config['exercises'][$programExerciseId] = $overrides;
            }
        }

        if (isset($config['userExercises']) && is_array($config['userExercises'])) {
            foreach ($config['userExercises'] as $userId => $byPivot) {
                if (! is_array($byPivot)) {
                    continue;
                }

                foreach ($byPivot as $programExerciseId => $overrides) {
                    if (! is_array($overrides)) {
                        continue;
                    }

                    unset(
                        $overrides['gridOverrides'],
                        $overrides['historicalGridOverrides'],
                        $overrides['baselineGridOverrides'],
                    );

                    $config['userExercises'][$userId][$programExerciseId] = $overrides;
                }
            }
        }

        return $config;
    }
};
