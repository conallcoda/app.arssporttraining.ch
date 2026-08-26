<?php

namespace App\Console\Commands;

use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Training\CarryOverAthleteValuesService;
use App\Training\TrainingSessionCompiler;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Throwable;

class RepairRecentCarryOverValuesCommand extends Command
{
    private const CARRIED_FIELDS = ['weight', 'reps', 'rest', 'tempo'];

    protected $signature = 'training:repair-recent-carry-over
        {--since= : Inclusive athlete-actual timestamp, for example 2026-08-12}
        {--until= : Inclusive athlete-actual timestamp, defaults to now}
        {--updated-by= : Required user id used for the repair audit}
        {--apply : Commit the reported future planned-value changes}
        {--report= : CSV report path; defaults below storage/app/reports}';

    protected $description = 'Audit or repair future carry-over projections from recently completed sessions using the production carry-over rules';

    public function handle(
        CarryOverAthleteValuesService $carryOver,
        TrainingValueSnapshotCodec $codec,
        TrainingSessionCompiler $sessionCompiler,
    ): int {
        try {
            [$since, $until, $updatedBy] = $this->validatedOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $sources = $this->sources($since, $until);

        $this->info(sprintf(
            '%s %d completed carry-over sources with explicit athlete values recorded from %s through %s.',
            $apply ? 'Repairing' : 'Auditing',
            $sources->count(),
            $since->toDateTimeString(),
            $until->toDateTimeString(),
        ));

        if ($sources->isEmpty()) {
            return self::SUCCESS;
        }

        Auth::loginUsingId($updatedBy);
        $programIds = $sources->pluck('slot.training_program_id')->filter()->unique()->values()->all();
        $before = $this->plannedSnapshot($programIds, $codec, $sessionCompiler);
        $changedSources = 0;

        DB::beginTransaction();

        try {
            foreach ($sources as $source) {
                if ($carryOver->carryFrom($source)) {
                    $changedSources++;
                }
            }

            $after = $this->plannedSnapshot($programIds, $codec, $sessionCompiler);
            $changes = $this->diff($before, $after);
            $reportPath = $this->writeReport($changes, $since, $until, $apply);

            if ($apply) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $this->renderSummary($changes, $changedSources, $apply, $reportPath);
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error('No changes committed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            Auth::logout();
        }

        return self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: int} */
    private function validatedOptions(): array
    {
        $sinceValue = trim((string) $this->option('since'));
        $untilValue = trim((string) $this->option('until'));
        $updatedBy = filter_var($this->option('updated-by'), FILTER_VALIDATE_INT);

        if ($sinceValue === '') {
            throw new InvalidArgumentException('Refusing to scan without --since.');
        }

        if ($updatedBy === false || $updatedBy <= 0) {
            throw new InvalidArgumentException('Refusing to run without a valid --updated-by user id.');
        }

        $since = Carbon::parse($sinceValue)->startOfDay();
        $until = $untilValue === '' ? now() : Carbon::parse($untilValue)->endOfDay();

        if ($since->gt($until)) {
            throw new InvalidArgumentException('--since must not be later than --until.');
        }

        return [$since, $until, (int) $updatedBy];
    }

    /** @return Collection<int, TrainingProgramSlotExercise> */
    private function sources(Carbon $since, Carbon $until)
    {
        return TrainingProgramSlotExercise::query()
            ->whereNotNull('exercise_program_exercise_id')
            ->where('status', TrainingProgramSlotExerciseStatusEnum::Completed)
            ->whereHas('slot', fn ($query) => $query
                ->where('status', TrainingProgramSlotStatusEnum::Completed)
                ->whereNull('cancelled_at'))
            ->whereHas('sets.values', fn ($query) => $query
                ->whereIn('setting_key', self::CARRIED_FIELDS)
                ->where('actual_is_explicit', true)
                ->whereNotNull('actual_value_type')
                ->whereBetween('actual_recorded_at', [$since, $until]))
            ->with(['slot', 'exercise'])
            ->get()
            ->sortBy(fn (TrainingProgramSlotExercise $exercise): string => sprintf(
                '%s:%010d:%010d',
                $exercise->slot?->datetime?->format('Y-m-d H:i:s') ?? '',
                $exercise->slot?->id ?? 0,
                $exercise->id,
            ))
            ->values();
    }

    /**
     * @param  list<int>  $programIds
     * @return array<int, array<string, mixed>>
     */
    private function plannedSnapshot(
        array $programIds,
        TrainingValueSnapshotCodec $codec,
        TrainingSessionCompiler $sessionCompiler,
    ): array {
        $blockIds = [];

        return TrainingProgramSlotSetValue::query()
            ->whereIn('setting_key', self::CARRIED_FIELDS)
            ->whereHas('slotSet.slotExercise.slot', fn ($query) => $query->whereIn('training_program_id', $programIds))
            ->with([
                'slotSet.slotExercise.exercise:id,name',
                'slotSet.slotExercise.slot.trainingProgram.program:id,exercise_category_id',
            ])
            ->get()
            ->mapWithKeys(function (TrainingProgramSlotSetValue $value) use ($codec, $sessionCompiler, &$blockIds): array {
                $set = $value->slotSet;
                $exercise = $set?->slotExercise;
                $slot = $exercise?->slot;
                $program = $slot?->trainingProgram;
                $slotId = (int) ($slot?->id ?? 0);

                if ($slotId > 0 && ! array_key_exists($slotId, $blockIds)) {
                    $blockIds[$slotId] = $sessionCompiler->categoryBlockForSlot($slot)?->id;
                }

                return [$value->id => [
                    'value_row_id' => $value->id,
                    'training_program_id' => $slot?->training_program_id,
                    'group_id' => $program?->group_id,
                    'plan_category_id' => $program?->program?->exercise_category_id,
                    'plan_block_id' => $blockIds[$slotId] ?? null,
                    'user_id' => $slot?->user_id,
                    'program_exercise_id' => $exercise?->exercise_program_exercise_id,
                    'exercise' => $exercise?->exercise?->name,
                    'slot_id' => $slot?->id,
                    'date' => $slot?->datetime?->toDateString(),
                    'status' => $slot?->status?->value,
                    'set' => $set?->set_number,
                    'field' => $value->setting_key,
                    'planned' => $codec->extractPlannedValue($value),
                ]];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     * @return list<array<string, mixed>>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $id => $row) {
            if (! array_key_exists($id, $before) || $this->valuesEquivalent($before[$id]['planned'], $row['planned'])) {
                continue;
            }

            $row['before'] = $before[$id]['planned'];
            $row['after'] = $row['planned'];
            unset($row['planned']);
            $row['link'] = $this->calendarUrl($row);
            $changes[] = $row;
        }

        usort($changes, fn (array $a, array $b): int => [
            $a['training_program_id'], $a['user_id'], $a['plan_block_id'], $a['date'], $a['program_exercise_id'], $a['set'], $a['field'],
        ] <=> [
            $b['training_program_id'], $b['user_id'], $b['plan_block_id'], $b['date'], $b['program_exercise_id'], $b['set'], $b['field'],
        ]);

        return $changes;
    }

    private function valuesEquivalent(mixed $before, mixed $after): bool
    {
        if (is_numeric($before) && is_numeric($after)) {
            return (float) $before === (float) $after;
        }

        return $before === $after;
    }

    /** @param list<array<string, mixed>> $changes */
    private function writeReport(array $changes, Carbon $since, Carbon $until, bool $apply): string
    {
        $path = trim((string) $this->option('report'));

        if ($path === '') {
            $path = storage_path('app/reports/carry-over-'.($apply ? 'applied' : 'dry-run').'-'.now()->format('Ymd-His').'.csv');
        }

        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException("Could not create report {$path}.");
        }

        fputcsv($handle, ['scan_since', 'scan_until', 'value_row_id', 'training_program_id', 'group_id', 'user_id', 'plan_category_id', 'plan_block_id', 'program_exercise_id', 'exercise', 'slot_id', 'date', 'status', 'set', 'field', 'before', 'after', 'link'], ',', '"', '');

        foreach ($changes as $row) {
            fputcsv($handle, [
                $since->toDateTimeString(), $until->toDateTimeString(), $row['value_row_id'],
                $row['training_program_id'], $row['group_id'], $row['user_id'], $row['plan_category_id'],
                $row['plan_block_id'], $row['program_exercise_id'],
                $row['exercise'], $row['slot_id'], $row['date'], $row['status'], $row['set'], $row['field'],
                $this->formatValue($row['before']), $this->formatValue($row['after']), $row['link'],
            ], ',', '"', '');
        }

        fclose($handle);

        return $path;
    }

    /** @param list<array<string, mixed>> $changes */
    private function renderSummary(array $changes, int $changedSources, bool $apply, string $reportPath): void
    {
        $groups = collect($changes)->groupBy(fn (array $row): string => implode(':', [
            $row['training_program_id'], $row['user_id'], $row['plan_block_id'], $row['program_exercise_id'],
        ]));

        $this->table(
            ['Program', 'Athlete', 'Block', 'Exercise', 'Cells', 'Sessions', 'Review'],
            $groups->map(function ($rows): array {
                $first = $rows->first();

                return [
                    $first['training_program_id'], $first['user_id'], $first['plan_block_id'] ?? 'ungrouped',
                    $first['exercise'], $rows->count(),
                    $rows->pluck('slot_id')->unique()->count(), $first['link'],
                ];
            })->values()->all(),
        );

        $this->info(sprintf(
            '%s %d planned cells across %d sessions from %d effective sources.',
            $apply ? 'Changed' : 'Would change',
            count($changes),
            collect($changes)->pluck('slot_id')->unique()->count(),
            $changedSources,
        ));
        $this->line('CSV report: '.$reportPath);
        $this->line('Athlete actuals, completion state, automatic/1RM exercises, and later coach-entered target cells were not changed.');
    }

    /** @param array<string, mixed> $row */
    private function calendarUrl(array $row): string
    {
        return 'https://app.arssporttraining.ch/admin/calendar?'.http_build_query([
            'preset' => 'thisMonth',
            'groupFilter' => 'all',
            'group' => $row['group_id'],
            'user' => $row['user_id'],
            'planCategory' => $row['plan_category_id'],
            'planBlock' => $row['plan_block_id'] ?? 'ungrouped',
            'planProgram' => $row['training_program_id'],
            'view' => 'plan',
        ]);
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }
}
