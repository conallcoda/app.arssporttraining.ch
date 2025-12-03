<?php

namespace App\Models\Training\Progression\Strategy;

use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Override\SetOverride;
use App\Models\Training\Progression\Result\ExerciseProgression;
use App\Models\Training\Progression\Result\SessionResult;
use App\Models\Training\Progression\Result\SetResult;
use App\Models\Training\Progression\Result\WeekResult;
use App\Models\Training\Progression\Strategy\Rep\RepStrategyInterface;
use App\Models\Training\Progression\Strategy\Weight\WeightStrategyInterface;

class ProgressionBase
{
    protected WeightStrategyInterface $weightStrategy;

    protected RepStrategyInterface $repStrategy;

    public function __construct(
        protected ProgressionConfig $globalConfig,
        protected OverrideStore $overrides,
        protected ?AthleteData $athlete = null,
    ) {}

    public function calculateExerciseProgression(
        int $exerciseId,
        array $sessionMap,
    ): ExerciseProgression {
        $config = $this->globalConfig->forExercise($exerciseId);
        $this->weightStrategy = $this->resolveWeightStrategy($config);
        $this->repStrategy = $this->resolveRepStrategy($config);

        $derived1RM = $this->getDerived1RM($exerciseId, $config);
        $target1RM = $this->weightStrategy->getTarget1RM($config, $derived1RM);

        $weeks = [];
        for ($weekIndex = 0; $weekIndex < $config->blockLength; $weekIndex++) {
            $weekSessions = $sessionMap[$weekIndex] ?? [];
            $weeks[] = $this->calculateWeekResult(
                $config,
                $exerciseId,
                $weekIndex,
                $derived1RM,
                $target1RM,
                $weekSessions,
            );
        }

        return new ExerciseProgression(
            exerciseId: $exerciseId,
            derived1RM: $derived1RM,
            target1RM: $target1RM,
            config: $config,
            weeks: $weeks,
        );
    }

    public function calculateWeekResult(
        ResolvedExerciseConfig $config,
        int $exerciseId,
        int $weekIndex,
        float $derived1RM,
        float $target1RM,
        array $weekSessions,
    ): WeekResult {
        $anchorOverride = $this->overrides->findApplicableAnchorOverride($exerciseId, $weekIndex);

        if ($anchorOverride !== null) {
            $anchorWeight = $anchorOverride->value;
        } else {
            $anchorWeight = $this->weightStrategy->calculateAnchorWeight($config, $derived1RM, $weekIndex);
        }

        $anchorReps = $config->getAnchorRepsForWeek($weekIndex);
        $projected1RM = $this->weightStrategy->getProjected1RM($config, $derived1RM, $weekIndex);
        $reference1RM = $this->repStrategy->getReference1RM($config, $derived1RM, $target1RM, $weekIndex);

        $sessions = [];
        foreach ($weekSessions as $sessionUuid => $sessionData) {
            $sessions[] = $this->calculateSessionResult(
                $config,
                $exerciseId,
                $weekIndex,
                $sessionUuid,
                $sessionData['day'] ?? 0,
                $sessionData['slot'] ?? 0,
                $anchorWeight,
                $reference1RM,
            );
        }

        return new WeekResult(
            weekIndex: $weekIndex,
            anchorWeight: $anchorWeight,
            anchorReps: $anchorReps,
            projected1RM: $projected1RM,
            reference1RM: $reference1RM,
            sessions: $sessions,
        );
    }

    public function calculateSessionResult(
        ResolvedExerciseConfig $config,
        int $exerciseId,
        int $weekIndex,
        string $sessionUuid,
        int $day,
        int $slot,
        float $anchorWeight,
        float $reference1RM,
    ): SessionResult {
        $totalSets = $config->getSetCountForWeek($weekIndex);
        $sets = [];

        for ($setIndex = 0; $setIndex < $totalSets; $setIndex++) {
            $sets[] = $this->calculateSetResult(
                $config,
                $exerciseId,
                $weekIndex,
                $sessionUuid,
                $setIndex,
                $totalSets,
                $anchorWeight,
                $reference1RM,
            );
        }

        return new SessionResult(
            sessionUuid: $sessionUuid,
            day: $day,
            slot: $slot,
            sets: $sets,
        );
    }

    public function calculateSetResult(
        ResolvedExerciseConfig $config,
        int $exerciseId,
        int $weekIndex,
        string $sessionUuid,
        int $setIndex,
        int $totalSets,
        float $anchorWeight,
        float $reference1RM,
    ): SetResult {
        $key = OverrideStore::buildSetKey($exerciseId, $weekIndex, $sessionUuid, $setIndex);
        $override = $this->overrides->getSetOverride($key);

        $computedWeight = $this->weightStrategy->calculateSetWeight($config, $anchorWeight, $setIndex, $totalSets);
        $computedReps = $this->repStrategy->calculateSetReps(
            $config,
            $weekIndex,
            $setIndex,
            $totalSets,
            $computedWeight,
            $reference1RM,
        );

        if ($override !== null && $override->locked) {
            return new SetResult(
                setIndex: $setIndex,
                reps: $override->reps ?? $computedReps,
                weight: $override->weight ?? $computedWeight,
                source: 'locked',
                computedReps: $computedReps,
                computedWeight: $computedWeight,
            );
        }

        if ($override !== null && $override->hasAnyOverride()) {
            return new SetResult(
                setIndex: $setIndex,
                reps: $override->reps ?? $computedReps,
                weight: $override->weight ?? $computedWeight,
                source: 'manual',
                computedReps: $computedReps,
                computedWeight: $computedWeight,
            );
        }

        return new SetResult(
            setIndex: $setIndex,
            reps: $computedReps,
            weight: $computedWeight,
            source: 'computed',
        );
    }

    public function setSetOverride(
        int $exerciseId,
        int $weekIndex,
        string $sessionUuid,
        int $setIndex,
        ?int $reps = null,
        ?float $weight = null,
    ): void {
        $key = OverrideStore::buildSetKey($exerciseId, $weekIndex, $sessionUuid, $setIndex);

        $existing = $this->overrides->getSetOverride($key);

        $this->overrides->setSetOverride($key, new SetOverride(
            reps: $reps ?? $existing?->reps,
            weight: $weight ?? $existing?->weight,
            locked: $existing?->locked ?? false,
            lockedAt: $existing?->lockedAt,
        ));
    }

    public function clearSetOverride(
        int $exerciseId,
        int $weekIndex,
        string $sessionUuid,
        int $setIndex,
    ): void {
        $key = OverrideStore::buildSetKey($exerciseId, $weekIndex, $sessionUuid, $setIndex);
        $this->overrides->removeSetOverride($key);
    }

    protected function getDerived1RM(int $exerciseId, ResolvedExerciseConfig $config): float
    {
        if ($this->athlete === null) {
            return 70.0;
        }

        $derived = $this->athlete->getDerived1RM($exerciseId, $config->modifier);

        return $derived ?? 70.0;
    }

    protected function resolveWeightStrategy(ResolvedExerciseConfig $config): WeightStrategyInterface
    {
        $strategyClass = $config->weightConfig->getStrategyClass();

        return new $strategyClass;
    }

    protected function resolveRepStrategy(ResolvedExerciseConfig $config): RepStrategyInterface
    {
        $strategyClass = $config->repConfig->getStrategyClass();

        return new $strategyClass;
    }

    public function getOverrideStore(): OverrideStore
    {
        return $this->overrides;
    }

    public function getGlobalConfig(): ProgressionConfig
    {
        return $this->globalConfig;
    }
}
