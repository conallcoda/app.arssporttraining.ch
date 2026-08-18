<?php

namespace App\Console\Commands;

use App\Casts\ExercisePlanConfigCast;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Training\Derivation\AutomaticHeartRateResolver;
use App\Training\TrainingSessionRebuildService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairAutomaticHeartRateOverridesCommand extends Command
{
    protected $signature = 'training:repair-automatic-heart-rate-overrides
        {trainingProgramId : Calendar training program id to repair}
        {--fallback-max-hr=193 : System fallback max HR used by the athlete-less preview}
        {--fallback-iat=90 : System fallback IAT percentage used by the athlete-less preview}
        {--dry-run : Report matching rows without changing data}
        {--no-rebuild : Do not rebuild editable calendar slots after repair}';

    protected $description = 'Remove imported automatic heart-rate values calculated from the athlete-less preview fallback';

    public function handle(
        AutomaticHeartRateResolver $heartRateResolver,
        TrainingSessionRebuildService $rebuildService,
    ): int {
        $trainingProgramId = (int) $this->argument('trainingProgramId');
        $fallbackMaxHR = (int) $this->option('fallback-max-hr');
        $fallbackIat = (int) $this->option('fallback-iat');
        $dryRun = (bool) $this->option('dry-run');

        if ($trainingProgramId <= 0 || $fallbackMaxHR <= 0 || $fallbackIat < 0) {
            $this->error('Training program id and fallback max HR must be positive; fallback IAT cannot be negative.');

            return self::FAILURE;
        }

        $trainingProgram = TrainingProgram::query()
            ->with(['program.exercises' => fn ($relation) => $relation->withTrashed()])
            ->find($trainingProgramId);

        if (! $trainingProgram || ! $trainingProgram->program instanceof ExerciseProgram) {
            $this->error("Training program {$trainingProgramId} or its exercise program was not found.");

            return self::FAILURE;
        }

        $program = $trainingProgram->program;
        $programConfig = $program->config;
        $matchingIds = collect();

        foreach ($program->exercises as $exercise) {
            $programExerciseId = (int) $exercise->pivot->id;
            $effectiveConfig = EffectiveExerciseConfig::resolve(
                $exercise->config,
                $programConfig->defaultExerciseOverrides($programExerciseId),
            );
            $mode = (string) data_get($effectiveConfig, 'heartRate.mode');

            if (! in_array($mode, ['automatic_biking', 'automatic_jogging'], true)) {
                continue;
            }

            $rows = $this->defaultRows($program, $programExerciseId);
            $zoneRows = $rows
                ->where('setting_key', 'heartRateZone')
                ->keyBy(fn (ExercisePlanConfigOverride $row): string => $this->coordinateKey($row));
            $exerciseMatchingIds = $rows
                ->where('setting_key', 'heartRate')
                ->filter(function (ExercisePlanConfigOverride $row) use ($zoneRows, $mode, $fallbackMaxHR, $fallbackIat, $heartRateResolver): bool {
                    $zoneRow = $zoneRows->get($this->coordinateKey($row));

                    if (! $zoneRow instanceof ExercisePlanConfigOverride) {
                        return false;
                    }

                    $zone = $zoneRow->getDecodedValue();
                    $heartRate = $row->getDecodedValue();

                    if (! is_scalar($zone) || ! is_scalar($heartRate)) {
                        return false;
                    }

                    $fallbackRange = $heartRateResolver->resolveRange(
                        $mode,
                        (string) $zone,
                        $fallbackMaxHR,
                        $fallbackIat,
                    );

                    return $fallbackRange !== null && trim((string) $heartRate) === $fallbackRange;
                })
                ->pluck('id');

            if ($exerciseMatchingIds->isNotEmpty()) {
                $this->line(sprintf(
                    '%s (program exercise %d): %d fallback-derived BPM override(s)',
                    $exercise->name,
                    $programExerciseId,
                    $exerciseMatchingIds->count(),
                ));
                $matchingIds->push(...$exerciseMatchingIds);
            }
        }

        $matchingIds = $matchingIds->unique()->values();

        if ($matchingIds->isEmpty()) {
            $this->info('No fallback-derived automatic heart-rate overrides were found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d BPM override row(s) from training program %d / exercise program %d.',
            $dryRun ? 'Would remove' : 'Removing',
            $matchingIds->count(),
            $trainingProgram->id,
            $program->id,
        ));

        if ($dryRun) {
            $this->comment('Dry run only: no rows were removed and no calendar slots were rebuilt.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(fn (): int => ExercisePlanConfigOverride::query()
            ->whereIn('id', $matchingIds->all())
            ->delete());

        ExercisePlanConfigCast::forgetOverrideRowsFor($program);

        if (! (bool) $this->option('no-rebuild')) {
            $rebuildService->rebuildOpenSlotsForTrainingProgram($trainingProgram->id);
            $this->info('Rebuilt editable calendar slots; completed/immutable sessions were left unchanged.');
        }

        $this->info("Removed {$deleted} fallback-derived BPM override row(s). Heart-rate-zone overrides were preserved.");

        return self::SUCCESS;
    }

    /** @return Collection<int, ExercisePlanConfigOverride> */
    private function defaultRows(ExerciseProgram $program, int $programExerciseId): Collection
    {
        return ExercisePlanConfigOverride::query()
            ->where('owner_type', $program->getMorphClass())
            ->where('owner_id', $program->id)
            ->where('program_exercise_id', $programExerciseId)
            ->whereNull('user_id')
            ->whereIn('setting_key', ['heartRate', 'heartRateZone'])
            ->get();
    }

    private function coordinateKey(ExercisePlanConfigOverride $row): string
    {
        return implode(':', [
            $row->scope,
            $row->target,
            $row->week_index,
            $row->session_index,
            $row->set_index ?? 'session',
        ]);
    }
}
