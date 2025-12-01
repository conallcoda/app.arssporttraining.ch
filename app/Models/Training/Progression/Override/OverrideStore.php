<?php

namespace App\Models\Training\Progression\Override;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class OverrideStore extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(SetOverride::class)]
        public array $setOverrides = [],
        #[DataCollectionOf(WeekAnchorOverride::class)]
        public array $weekAnchorOverrides = [],
        public array $impliedValues = [],
    ) {}

    public static function buildSetKey(
        int $exerciseId,
        int $weekIndex,
        string $sessionUuid,
        int $setIndex
    ): string {
        return "exercise:{$exerciseId}:week:{$weekIndex}:session:{$sessionUuid}:set:{$setIndex}";
    }

    public static function buildWeekKey(int $exerciseId, int $weekIndex): string
    {
        return "exercise:{$exerciseId}:week:{$weekIndex}";
    }

    public static function buildSessionKey(int $exerciseId, int $weekIndex, string $sessionUuid): string
    {
        return "exercise:{$exerciseId}:week:{$weekIndex}:session:{$sessionUuid}";
    }

    public function getSetOverride(string $key): ?SetOverride
    {
        return $this->setOverrides[$key] ?? null;
    }

    public function setSetOverride(string $key, SetOverride $override): static
    {
        $this->setOverrides[$key] = $override;

        return $this;
    }

    public function removeSetOverride(string $key): static
    {
        unset($this->setOverrides[$key]);

        return $this;
    }

    public function getWeekAnchorOverride(string $key): ?WeekAnchorOverride
    {
        return $this->weekAnchorOverrides[$key] ?? null;
    }

    public function setWeekAnchorOverride(string $key, WeekAnchorOverride $override): static
    {
        $this->weekAnchorOverrides[$key] = $override;

        return $this;
    }

    public function removeWeekAnchorOverride(string $key): static
    {
        unset($this->weekAnchorOverrides[$key]);

        return $this;
    }

    public function findApplicableAnchorOverride(int $exerciseId, int $weekIndex): ?WeekAnchorOverride
    {
        $exactKey = self::buildWeekKey($exerciseId, $weekIndex);
        $exactOverride = $this->getWeekAnchorOverride($exactKey);

        if ($exactOverride !== null) {
            return $exactOverride;
        }

        $applicable = null;
        $latestFromWeek = -1;

        foreach ($this->weekAnchorOverrides as $key => $override) {
            if (! str_starts_with($key, "exercise:{$exerciseId}:")) {
                continue;
            }

            if ($override->isCarryForward() && $override->appliesTo($weekIndex)) {
                if ($override->fromWeek > $latestFromWeek) {
                    $latestFromWeek = $override->fromWeek;
                    $applicable = $override;
                }
            }
        }

        return $applicable;
    }

    public function isLocked(string $key): bool
    {
        $override = $this->getSetOverride($key);

        return $override?->locked ?? false;
    }

    public function lockSet(string $key, ?int $reps = null, ?float $weight = null): static
    {
        $existing = $this->getSetOverride($key) ?? new SetOverride;

        $this->setOverrides[$key] = new SetOverride(
            reps: $reps ?? $existing->reps,
            weight: $weight ?? $existing->weight,
            locked: true,
            lockedAt: now(),
        );

        return $this;
    }

    public function lockSession(int $exerciseId, int $weekIndex, string $sessionUuid, array $setValues): static
    {
        foreach ($setValues as $setIndex => $values) {
            $key = self::buildSetKey($exerciseId, $weekIndex, $sessionUuid, $setIndex);
            $this->lockSet($key, $values['reps'] ?? null, $values['weight'] ?? null);
        }

        return $this;
    }

    public function setImpliedValue(string $key, float $value): static
    {
        $this->impliedValues[$key] = $value;

        return $this;
    }

    public function getImpliedValue(string $key): ?float
    {
        return $this->impliedValues[$key] ?? null;
    }

    public function clearExerciseOverrides(int $exerciseId): static
    {
        $prefix = "exercise:{$exerciseId}:";

        $this->setOverrides = array_filter(
            $this->setOverrides,
            fn ($key) => ! str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        $this->weekAnchorOverrides = array_filter(
            $this->weekAnchorOverrides,
            fn ($key) => ! str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        $this->impliedValues = array_filter(
            $this->impliedValues,
            fn ($key) => ! str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        return $this;
    }
}
