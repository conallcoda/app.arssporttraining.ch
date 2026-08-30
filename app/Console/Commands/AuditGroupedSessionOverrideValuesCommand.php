<?php

namespace App\Console\Commands;

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Training\TrainingSessionCompiler;
use App\Training\TrainingSessionEditGuard;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AuditGroupedSessionOverrideValuesCommand extends Command
{
    protected $signature = 'training:audit-grouped-session-override-values
        {--training-program=* : Limit the audit to training program ids}
        {--imported-from=2026-08-20 : Include calendar programs imported on or after this date}
        {--imported-to= : Include calendar programs imported on or before this date}
        {--from= : Include slots on or after this date}
        {--to= : Include slots on or before this date}';

    protected $description = 'Read-only audit of stored planned values that differ from grouped-session overrides';

    public function handle(
        TrainingSessionCompiler $compiler,
        TrainingSessionEditGuard $editGuard,
        TrainingValueSnapshotCodec $valueCodec,
    ): int {
        $trainingProgramIds = collect($this->option('training-program'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        $from = $this->dateOption('from', startOfDay: true);
        $to = $this->dateOption('to', startOfDay: false);
        $importedFrom = $this->dateOption('imported-from', startOfDay: true);
        $importedTo = $this->dateOption('imported-to', startOfDay: false);

        if (($this->option('from') && $from === null)
            || ($this->option('to') && $to === null)
            || ($this->option('imported-from') && $importedFrom === null)
            || ($this->option('imported-to') && $importedTo === null)) {
            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $report */
        $report = [];
        $candidateSlots = 0;

        TrainingProgramSlot::query()
            ->whereNull('cancelled_at')
            ->when($trainingProgramIds !== [], fn (Builder $query) => $query->whereIn('training_program_id', $trainingProgramIds))
            ->whereHas('trainingProgram', fn (Builder $query) => $query
                ->when($importedFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', $importedFrom))
                ->when($importedTo !== null, fn (Builder $query) => $query->where('created_at', '<=', $importedTo)))
            ->when($from !== null, fn (Builder $query) => $query->where('datetime', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('datetime', '<=', $to))
            ->with([
                'trainingProgram.program.exercises' => fn ($relation) => $relation
                    ->orderByPivot('type')
                    ->orderByPivot('sort')
                    ->orderByPivot('id'),
                'exercises.sets.values',
            ])
            ->chunkById(100, function (Collection $slots) use (
                $compiler,
                $editGuard,
                $valueCodec,
                &$report,
                &$candidateSlots,
            ): void {
                foreach ($slots as $slot) {
                    $settingsByExercise = $this->groupedOverrideSettings($slot);

                    if ($settingsByExercise === []) {
                        continue;
                    }

                    $candidateSlots++;
                    $compiled = $compiler->compile($slot);
                    $compiledByExercise = collect($compiled->exercises)->keyBy('programExerciseId');
                    $storedByExercise = $slot->exercises->keyBy('exercise_program_exercise_id');
                    $differences = [];

                    foreach ($settingsByExercise as $programExerciseId => $settings) {
                        $differences = array_merge($differences, $this->valueDifferences(
                            $storedByExercise->get($programExerciseId),
                            $compiledByExercise->get($programExerciseId),
                            $settings,
                            $valueCodec,
                        ));
                    }

                    if ($differences === []) {
                        continue;
                    }

                    $trainingProgram = $slot->trainingProgram;
                    $program = $trainingProgram?->program;

                    if ($trainingProgram === null || $program === null) {
                        continue;
                    }

                    $trainingProgramId = (int) $trainingProgram->id;
                    $report[$trainingProgramId] ??= [
                        'training_program_id' => $trainingProgramId,
                        'exercise_program_id' => (int) $program->id,
                        'group_id' => (int) $trainingProgram->group_id,
                        'name' => (string) $program->name,
                        'slots' => 0,
                        'values' => 0,
                        'mutable' => 0,
                        'immutable' => 0,
                        'users' => [],
                        'settings' => [],
                        'first' => null,
                        'last' => null,
                    ];

                    $row = &$report[$trainingProgramId];
                    $row['slots']++;
                    $row['values'] += count($differences);
                    $row[$editGuard->isImmutableSlot($slot) ? 'immutable' : 'mutable']++;
                    $row['users'][(int) $slot->user_id] = true;

                    foreach ($differences as $difference) {
                        $row['settings'][$difference['setting']] = true;
                    }

                    $date = $slot->datetime->format('Y-m-d');
                    $row['first'] = $row['first'] === null || $date < $row['first'] ? $date : $row['first'];
                    $row['last'] = $row['last'] === null || $date > $row['last'] ? $date : $row['last'];
                }
            });

        ksort($report);
        $rows = collect($report)->map(fn (array $row): array => [
            $row['training_program_id'],
            $row['exercise_program_id'],
            $row['group_id'],
            $row['name'],
            $row['slots'],
            $row['values'],
            $row['mutable'],
            $row['immutable'],
            count($row['users']),
            implode(', ', array_keys($row['settings'])),
            $row['first'],
            $row['last'],
        ])->all();

        if ($rows !== []) {
            $this->table(
                ['Training', 'Program', 'Group', 'Name', 'Wrong slots', 'Wrong values', 'Mutable', 'Immutable', 'Users', 'Settings', 'First', 'Last'],
                $rows,
            );
        }

        $wrongSlots = collect($report)->sum('slots');
        $wrongValues = collect($report)->sum('values');

        $this->info(sprintf(
            'Read-only audit for imports from %s: %d candidate slots, %d programs with actual planned-value differences, %d wrong slots, %d wrong values.',
            $importedFrom?->toDateString() ?? 'any date',
            $candidateSlots,
            count($report),
            $wrongSlots,
            $wrongValues,
        ));

        return self::SUCCESS;
    }

    /** @return array<int, list<string>> */
    private function groupedOverrideSettings(TrainingProgramSlot $slot): array
    {
        $program = $slot->trainingProgram?->program;

        if ($program === null) {
            return [];
        }

        $result = [];

        foreach ($program->exercises as $exercise) {
            $programExerciseId = (int) $exercise->pivot->id;
            $resolved = $program->config->resolveExercise(
                $exercise->config,
                $programExerciseId,
                (int) $slot->user_id,
            );

            if (data_get($resolved->effectiveConfig, 'preview.groupingMode') !== SessionGroupingMode::Groups->value) {
                continue;
            }

            $settings = [];

            foreach (['sessions', 'cells'] as $target) {
                foreach ($resolved->overrideLayer[$target] ?? [] as $override) {
                    foreach (array_keys($override['data'] ?? []) as $setting) {
                        $settings[$setting] = true;
                    }
                }
            }

            if ($settings !== []) {
                $result[$programExerciseId] = array_keys($settings);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $settings
     * @return list<array{set: int, setting: string, stored: mixed, expected: mixed}>
     */
    private function valueDifferences(
        ?TrainingProgramSlotExercise $storedExercise,
        ?CompiledTrainingExercise $compiledExercise,
        array $settings,
        TrainingValueSnapshotCodec $valueCodec,
    ): array {
        $stored = [];

        foreach ($storedExercise?->sets ?? [] as $set) {
            foreach ($set->values as $value) {
                if (in_array($value->setting_key, $settings, true)) {
                    $stored[$set->set_number][$value->setting_key] = $valueCodec->extractPlannedValue($value);
                }
            }
        }

        $expected = [];

        foreach ($compiledExercise?->sets ?? [] as $set) {
            foreach ($set->values as $value) {
                if (in_array($value->settingKey, $settings, true)) {
                    $expected[$set->setNumber][$value->settingKey] = $value->plannedValue;
                }
            }
        }

        $differences = [];
        $setNumbers = array_unique([...array_keys($stored), ...array_keys($expected)]);

        foreach ($setNumbers as $setNumber) {
            foreach ($settings as $setting) {
                $storedValue = $stored[$setNumber][$setting] ?? null;
                $expectedValue = $expected[$setNumber][$setting] ?? null;

                if ($this->comparableValue($storedValue) !== $this->comparableValue($expectedValue)) {
                    $differences[] = [
                        'set' => (int) $setNumber,
                        'setting' => $setting,
                        'stored' => $storedValue,
                        'expected' => $expectedValue,
                    ];
                }
            }
        }

        return $differences;
    }

    private function comparableValue(mixed $value): string
    {
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function dateOption(string $name, bool $startOfDay): ?Carbon
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $startOfDay ? $date->startOfDay() : $date->endOfDay();
        } catch (\Throwable) {
            $this->error("Invalid --{$name} date: {$value}");

            return null;
        }
    }
}
