<?php

namespace App\Data\Training\Config;

use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use App\Data\Training\Config\Target\TargetConfig;
use Coda\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PlanTemplateConfig extends AbstractConfig
{
    public function __construct(
        public DefaultScheduleConfig|Optional $schedule,
        public TargetConfig|Optional $target,
        /** @var array<int, ExerciseOverrides> */
        public array $exercises = [],
    ) {}

    public static function initialize(): self
    {
        $weeks = array_map(fn (int $i) => [
            'id' => "default_{$i}",
            'linkedTo' => $i > 0 ? 'default_0' : null,
            'sort' => $i,
            'slots' => [],
        ], range(0, 4));

        return self::from([
            'schedule' => [
                'weeks' => $weeks,
            ],
            'target' => [
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
        ]);
    }

    public function defaultScheduleWeeks(): array
    {
        if ($this->schedule instanceof Optional) {
            return [];
        }

        return $this->toPlainArrays($this->schedule->weeks ?? []);
    }

    public function defaultScheduleStartDate(): ?string
    {
        return null;
    }

    public function setDefaultScheduleStartDate(string $startDate): void {}

    public function setDefaultScheduleWeeks(array $weeks): void
    {
        $this->schedule = DefaultScheduleConfig::from([
            'weeks' => $weeks,
        ]);
    }

    public function defaultTarget(): ?TargetConfig
    {
        if ($this->target instanceof Optional) {
            return null;
        }

        return $this->target;
    }

    public function defaultTargetMeasuredReps(): ?int
    {
        return $this->defaultTarget()?->measuredReps;
    }

    public function defaultTargetMeasuredWeight(): ?float
    {
        return $this->defaultTarget()?->measuredWeight;
    }

    public function defaultTargetGoal(): int|float
    {
        return $this->defaultTarget()?->targetGoal ?? 10;
    }

    public function setDefaultTarget(?int $measuredReps, ?float $measuredWeight, int|float $targetGoal): void
    {
        $this->target = TargetConfig::from([
            'measuredReps' => $measuredReps,
            'measuredWeight' => $measuredWeight,
            'targetGoal' => $targetGoal,
        ]);
    }

    public function defaultExerciseOverrides(int $exerciseId): ExerciseOverrides
    {
        $data = $this->exercises[$exerciseId] ?? null;

        if ($data === null) {
            return new ExerciseOverrides;
        }

        if ($data instanceof ExerciseOverrides) {
            return $data;
        }

        $overrides = ExerciseOverrides::from($data);
        $this->exercises[$exerciseId] = $overrides;

        return $overrides;
    }

    public function setDefaultExerciseOverrides(int $exerciseId, ExerciseOverrides $overrides): void
    {
        $this->exercises[$exerciseId] = $overrides;
    }

    public function removeExerciseOverrides(int $exerciseId): void
    {
        unset($this->exercises[$exerciseId]);
    }

    public function hasDefaultOverrides(): bool
    {
        return ! empty($this->exercises);
    }

    public function resetDefaults(): void
    {
        $this->exercises = [];
    }

    public function resetAll(): void
    {
        $this->resetDefaults();
    }

    public function removeProgramFromAllSchedules(int $programId): void
    {
        $weeks = $this->stripProgramFromWeeks($this->defaultScheduleWeeks(), $programId);
        $this->setDefaultScheduleWeeks($weeks);
    }

    protected function stripProgramFromWeeks(array $weeks, int $programId): array
    {
        foreach ($weeks as &$week) {
            if (($week['linkedTo'] ?? null) !== null) {
                continue;
            }

            $slots = $week['slots'] ?? [];
            foreach ($slots as &$slot) {
                $slot['programs'] = array_values(array_filter(
                    $slot['programs'] ?? [],
                    fn (int $id) => $id !== $programId
                ));
            }

            $week['slots'] = array_values(array_filter($slots, fn (array $s) => ! empty($s['programs'] ?? [])));
        }

        return $weeks;
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
