<?php

namespace App\Training;

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Models\Training\TrainingProgramSlotExercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AutomaticOneRepMaxSessionCountRepair
{
    public function __construct(
        private readonly TrainingSessionCompiler $compiler,
        private readonly TrainingPlanSessionCountResolver $sessionCountResolver,
        private readonly TrainingSessionEditGuard $editGuard,
        private readonly TrainingSessionMaterializer $materializer,
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    /**
     * @param  list<int>  $trainingProgramIds
     * @param  list<int>  $blockIds
     * @return Collection<int, array<string, mixed>>
     */
    public function scan(
        array $trainingProgramIds = [],
        array $blockIds = [],
        ?string $compiledSince = null,
    ): Collection {
        $rows = collect();

        $this->candidateQuery($trainingProgramIds, $compiledSince)
            ->chunkById(200, function (Collection $exercises) use ($rows, $blockIds): void {
                foreach ($exercises as $exercise) {
                    $candidate = $this->inspect($exercise, $blockIds);

                    if ($candidate !== null) {
                        $rows->push($candidate);
                    }
                }
            });

        return $rows
            ->sortBy([
                ['training_program_id', 'asc'],
                ['slot_date', 'asc'],
                ['athlete', 'asc'],
                ['exercise', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array{recompiled: int, preserved: int, missing: int}
     */
    public function apply(Collection $candidates): array
    {
        $result = ['recompiled' => 0, 'preserved' => 0, 'missing' => 0];

        foreach ($candidates->groupBy('slot_id') as $slotCandidates) {
            $first = $slotCandidates->first();
            $slot = $first['model']->slot;
            $slotResult = $this->materializer->materializeExercises(
                $slot,
                $slotCandidates->pluck('program_exercise_id')->all(),
            );

            foreach (array_keys($result) as $key) {
                $result[$key] += $slotResult[$key];
            }
        }

        return $result;
    }

    /** @param list<int> $trainingProgramIds */
    private function candidateQuery(array $trainingProgramIds, ?string $compiledSince): Builder
    {
        return TrainingProgramSlotExercise::query()
            ->whereHas('slot', function (Builder $query) use ($trainingProgramIds, $compiledSince): void {
                $query
                    ->whereNotNull('compiled_at')
                    ->whereNull('cancelled_at')
                    ->when($compiledSince !== null, fn (Builder $query) => $query
                        ->where('compiled_at', '>=', $compiledSince))
                    ->when($trainingProgramIds !== [], fn (Builder $query) => $query
                        ->whereIn('training_program_id', $trainingProgramIds));
            })
            ->whereHas('settingSnapshot', fn (Builder $query) => $query
                ->where('config->weight->mode', 'automatic'))
            ->with([
                'exercise:id,name',
                'settingSnapshot',
                'sets.values',
                'slot.user:id,forename,surname',
                'slot.trainingProgram.group:id,name',
                'slot.trainingProgram.program.exercises',
            ]);
    }

    /**
     * @param  list<int>  $blockIds
     * @return array<string, mixed>|null
     */
    private function inspect(TrainingProgramSlotExercise $exercise, array $blockIds): ?array
    {
        $config = $exercise->settingSnapshot?->config ?? [];
        $preview = $config['preview'] ?? [];

        if (($config['weight']['mode'] ?? null) !== 'automatic'
            || SessionGroupingMode::normalizeMode($preview['groupingMode'] ?? null) !== SessionGroupingMode::Groups->value) {
            return null;
        }

        $slot = $exercise->slot;
        $block = $this->compiler->categoryBlockForSlot($slot);

        if ($blockIds !== [] && ($block === null || ! in_array((int) $block->id, $blockIds, true))) {
            return null;
        }

        $context = $this->compiler->sessionContextForSlot($slot);
        $athleteScheduledCount = array_sum($context['weekSessionCounts']);
        $authoredCount = max(1, (int) $slot->trainingProgram->program->config->weeks)
            * SessionGroupingMode::resolvePreviewSessionCount($preview, 1);
        $oldSessionCount = max($authoredCount, $athleteScheduledCount, $context['slotIndex'] + 1);
        $newSessionCount = max(
            $this->sessionCountResolver->resolveForSlot($slot, $block),
            $athleteScheduledCount,
            $context['slotIndex'] + 1,
        );

        if ($oldSessionCount === $newSessionCount) {
            return null;
        }

        $compiledExercise = collect($this->compiler->compile($slot)->exercises)
            ->firstWhere('programExerciseId', (int) $exercise->exercise_program_exercise_id);

        if (! $compiledExercise instanceof CompiledTrainingExercise
            || $this->storedSignature($exercise) == $this->compiledSignature($compiledExercise)) {
            return null;
        }

        return [
            'model' => $exercise,
            'slot_exercise_id' => (int) $exercise->id,
            'slot_id' => (int) $slot->id,
            'slot_date' => ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d'),
            'training_program_id' => (int) $slot->training_program_id,
            'block_id' => $block?->id,
            'group' => $slot->trainingProgram->group?->name ?? (string) $slot->trainingProgram->group_id,
            'athlete' => trim((string) preg_replace(
                '/\s+/',
                ' ',
                ($slot->user?->forename ?? '').' '.($slot->user?->surname ?? ''),
            )) ?: (string) $slot->user_id,
            'exercise' => $exercise->exercise?->name ?? (string) $exercise->exercise_id,
            'program_exercise_id' => (int) $exercise->exercise_program_exercise_id,
            'old_session_count' => $oldSessionCount,
            'new_session_count' => $newSessionCount,
            'recorded' => $this->editGuard->aggregateColumnsIndicateRecordedExerciseOutcome($exercise),
        ];
    }

    /** @return array<int, mixed> */
    private function storedSignature(TrainingProgramSlotExercise $exercise): array
    {
        return $exercise->sets
            ->sortBy('set_number')
            ->map(fn ($set): array => [
                'set' => (int) $set->set_number,
                'values' => $set->values
                    ->sortBy('setting_key')
                    ->map(fn ($value): array => [
                        'key' => $value->setting_key,
                        'type' => $value->planned_value_type,
                        'value' => $this->valueCodec->extractPlannedValue($value),
                        'canonical' => $value->plannedCanonicalValue(),
                        'unit' => $value->unit,
                    ])->values()->all(),
            ])->values()->all();
    }

    /** @return array<int, mixed> */
    private function compiledSignature(CompiledTrainingExercise $exercise): array
    {
        return collect($exercise->sets)
            ->sortBy('setNumber')
            ->map(fn ($set): array => [
                'set' => $set->setNumber,
                'values' => collect($set->values)
                    ->sortBy('settingKey')
                    ->map(fn ($value): array => [
                        'key' => $value->settingKey,
                        'type' => $value->plannedValueType,
                        'value' => $value->plannedValue,
                        'canonical' => $value->plannedCanonicalValue,
                        'unit' => $value->unit,
                    ])->values()->all(),
            ])->values()->all();
    }
}
