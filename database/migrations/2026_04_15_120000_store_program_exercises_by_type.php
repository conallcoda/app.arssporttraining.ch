<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->string('type')->default('main')->after('group');
            $table->index(['exercise_program_id', 'type', 'sort'], 'epe_program_type_sort_idx');
        });

        DB::table('exercise_program_exercises')
            ->whereNull('type')
            ->update(['type' => 'main']);

        $programs = DB::table('exercise_programs')
            ->select('id', 'warm_up_program_id', 'warm_down_program_id', 'config')
            ->where(function ($query) {
                $query->whereNotNull('warm_up_program_id')
                    ->orWhereNotNull('warm_down_program_id');
            })
            ->orderBy('id')
            ->get();

        foreach ($programs as $program) {
            $mainPivots = DB::table('exercise_program_exercises')
                ->where('exercise_program_id', $program->id)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            $mainKeyMap = $this->mapExerciseIdsToPivotIds($mainPivots);
            $mergedConfig = $this->remapConfigKeys(
                $this->decodeConfig($program->config),
                $mainKeyMap
            );

            foreach ([
                'warm_up' => $program->warm_up_program_id,
                'warm_down' => $program->warm_down_program_id,
            ] as $type => $sourceProgramId) {
                if ($sourceProgramId === null) {
                    continue;
                }

                $sourceProgram = DB::table('exercise_programs')
                    ->select('id', 'config')
                    ->where('id', $sourceProgramId)
                    ->first();

                if ($sourceProgram === null) {
                    continue;
                }

                $sourcePivots = DB::table('exercise_program_exercises')
                    ->where('exercise_program_id', $sourceProgramId)
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get();

                $sourceKeyMap = [];

                foreach ($sourcePivots as $pivot) {
                    $newPivotId = DB::table('exercise_program_exercises')->insertGetId([
                        'exercise_program_id' => $program->id,
                        'exercise_id' => $pivot->exercise_id,
                        'sort' => $pivot->sort,
                        'group' => $pivot->group,
                        'type' => $type,
                        'created_at' => $pivot->created_at,
                        'updated_at' => $pivot->updated_at,
                    ]);

                    if (! isset($sourceKeyMap[$pivot->exercise_id])) {
                        $sourceKeyMap[$pivot->exercise_id] = [];
                    }

                    $sourceKeyMap[$pivot->exercise_id][] = $newPivotId;
                }

                $mergedConfig = $this->mergeConfigs(
                    $mergedConfig,
                    $this->remapConfigKeys($this->decodeConfig($sourceProgram->config), $sourceKeyMap)
                );
            }

            DB::table('exercise_programs')
                ->where('id', $program->id)
                ->update([
                    'config' => $mergedConfig === [] ? null : json_encode($mergedConfig),
                    'warm_up_program_id' => null,
                    'warm_down_program_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $programs = DB::table('exercise_programs')
            ->select('id')
            ->orderBy('id')
            ->get();

        foreach ($programs as $program) {
            $mainPivots = DB::table('exercise_program_exercises')
                ->where('exercise_program_id', $program->id)
                ->where('type', 'main')
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            $mainKeyMap = $this->mapExerciseIdsToPivotIds($mainPivots);
            $config = DB::table('exercise_programs')
                ->where('id', $program->id)
                ->value('config');

            DB::table('exercise_programs')
                ->where('id', $program->id)
                ->update([
                    'config' => ($remapped = $this->remapConfigKeys($this->decodeConfig($config), $mainKeyMap)) === []
                        ? null
                        : json_encode($remapped),
                    'updated_at' => now(),
                ]);
        }

        DB::table('exercise_program_exercises')
            ->whereIn('type', ['warm_up', 'warm_down'])
            ->delete();

        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->dropIndex('epe_program_type_sort_idx');
            $table->dropColumn('type');
        });
    }

    private function decodeConfig(?string $config): array
    {
        return $config ? (json_decode($config, true) ?: []) : [];
    }

    private function mapExerciseIdsToPivotIds($pivots): array
    {
        $map = [];

        foreach ($pivots as $pivot) {
            if (! isset($map[$pivot->exercise_id])) {
                $map[$pivot->exercise_id] = [];
            }

            $map[$pivot->exercise_id][] = $pivot->id;
        }

        return $map;
    }

    private function remapConfigKeys(array $config, array $keyMap): array
    {
        $config['exercises'] = $this->remapOverrideBag($config['exercises'] ?? [], $keyMap);

        $userExercises = [];
        foreach (($config['userExercises'] ?? []) as $userId => $overrides) {
            $userExercises[$userId] = $this->remapOverrideBag($overrides, $keyMap);
        }
        $config['userExercises'] = $userExercises;

        return $config;
    }

    private function remapOverrideBag(array $overrides, array $keyMap): array
    {
        $remapped = [];
        $position = [];

        foreach ($overrides as $legacyKey => $data) {
            $legacyId = (int) $legacyKey;
            $targetIds = $keyMap[$legacyId] ?? null;

            if ($targetIds === null || $targetIds === []) {
                continue;
            }

            $currentPosition = $position[$legacyId] ?? 0;
            $targetId = $targetIds[min($currentPosition, count($targetIds) - 1)];
            $position[$legacyId] = $currentPosition + 1;
            $remapped[$targetId] = $data;
        }

        return $remapped;
    }

    private function mergeConfigs(array $target, array $source): array
    {
        $target['exercises'] = array_merge(
            $target['exercises'] ?? [],
            $source['exercises'] ?? [],
        );

        $mergedUserExercises = $target['userExercises'] ?? [];
        foreach (($source['userExercises'] ?? []) as $userId => $overrides) {
            $mergedUserExercises[$userId] = array_merge(
                $mergedUserExercises[$userId] ?? [],
                $overrides
            );
        }
        $target['userExercises'] = $mergedUserExercises;

        return $target;
    }
};
