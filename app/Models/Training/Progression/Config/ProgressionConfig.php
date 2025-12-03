<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Data\Form\FluxFieldset;
use App\Models\Training\Progression\Casts\RepConfigCast;
use App\Models\Training\Progression\Casts\WeightConfigCast;
use App\Models\Training\Progression\Strategy\Rep\FixedRepConfig;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Rep\RepConfigInterface;
use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\WeightConfigInterface;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;

class ProgressionConfig extends AbstractData
{
    public function __construct(
        public int $blockLength = 5,
        #[WithCast(WeightConfigCast::class)]
        public WeightConfigInterface $weightConfig = new FixedStepWeightConfig,
        #[WithCast(RepConfigCast::class)]
        public RepConfigInterface $repConfig = new PairedLadderRepConfig,
        #[DataCollectionOf(ExerciseConfig::class)]
        public array $exerciseOverrides = [],
    ) {}

    public function forExercise(int $exerciseId): ResolvedExerciseConfig
    {
        $override = $this->getExerciseOverride($exerciseId);

        $weightConfig = $override?->weightConfig ?? $this->weightConfig;
        $repConfig = $override?->repConfig ?? $this->repConfig;

        return new ResolvedExerciseConfig(
            exerciseId: $exerciseId,
            blockLength: $this->blockLength,
            weightConfig: $weightConfig,
            repConfig: $repConfig,
            modifier: $override?->modifier ?? 1.0,
        );
    }

    public function getExerciseOverride(int $exerciseId): ?ExerciseConfig
    {
        return $this->exerciseOverrides[$exerciseId] ?? null;
    }

    public function setExerciseOverride(int $exerciseId, string $key, mixed $value): static
    {
        if (! isset($this->exerciseOverrides[$exerciseId])) {
            $this->exerciseOverrides[$exerciseId] = new ExerciseConfig(exerciseId: $exerciseId);
        }

        $this->exerciseOverrides[$exerciseId]->{$key} = $value;

        return $this;
    }

    public function clearExerciseOverride(int $exerciseId, ?string $key = null): static
    {
        if ($key === null) {
            unset($this->exerciseOverrides[$exerciseId]);
        } elseif (isset($this->exerciseOverrides[$exerciseId])) {
            $this->exerciseOverrides[$exerciseId]->{$key} = null;
        }

        return $this;
    }

    public function hasExerciseOverride(int $exerciseId, ?string $key = null): bool
    {
        $override = $this->exerciseOverrides[$exerciseId] ?? null;

        if ($override === null) {
            return false;
        }

        if ($key === null) {
            return true;
        }

        return $override->hasOverride($key);
    }

    public function buildOverview(int $totalSessions, int $minSessionsPerWeek, int $maxSessionsPerWeek): BlockOverview
    {
        return new BlockOverview(
            weekCount: $this->blockLength,
            totalSessions: $totalSessions,
            minSessionsPerWeek: $minSessionsPerWeek,
            maxSessionsPerWeek: $maxSessionsPerWeek,
            weightStrategy: $this->getWeightStrategyLabel(),
            repStrategy: $this->getRepStrategyLabel(),
            targetImprovement: $this->weightConfig->targetImprovement,
        );
    }

    public function getWeightStrategyLabel(): string
    {
        return match (true) {
            $this->weightConfig instanceof CompoundedWeightConfig => 'Compounded',
            $this->weightConfig instanceof FixedStepWeightConfig => 'Fixed Step',
            default => 'Unknown',
        };
    }

    public function getRepStrategyLabel(): string
    {
        return match (true) {
            $this->repConfig instanceof FixedRepConfig => 'Fixed',
            $this->repConfig instanceof PairedLadderRepConfig => 'Paired Ladder',
            default => 'Unknown',
        };
    }

    public static function getWeightConfigForStrategy(string $strategy): FixedStepWeightConfig|CompoundedWeightConfig
    {
        return match ($strategy) {
            'compounded' => new CompoundedWeightConfig,
            default => new FixedStepWeightConfig,
        };
    }

    public static function getRepConfigForStrategy(string $strategy): FixedRepConfig|PairedLadderRepConfig
    {
        return match ($strategy) {
            'fixed' => new FixedRepConfig,
            default => new PairedLadderRepConfig,
        };
    }

    public static function getFields(array $values = []): array
    {
        $weightConfig = self::getWeightConfigForStrategy($values['weightStrategy'] ?? 'fixed_step');
        $repConfig = self::getRepConfigForStrategy($values['repStrategy'] ?? 'paired_ladder');

        return [
            FluxFieldset::make('Weight Configuration')->schema([
                FluxField::select('weightStrategy')
                    ->label('Weight Strategy')
                    ->options([
                        'fixed_step' => 'Fixed Step',
                        'compounded' => 'Compounded',
                    ])
                    ->required()
                    ->live()
                    ->rules('required|in:fixed_step,compounded'),
                ...$weightConfig->getFields(),
            ]),
            FluxFieldset::make('Rep Configuration')->schema([
                FluxField::select('repStrategy')
                    ->label('Rep Strategy')
                    ->options([
                        'paired_ladder' => 'Paired Ladder',
                        'fixed' => 'Fixed',
                    ])
                    ->required()
                    ->live()
                    ->rules('required|in:paired_ladder,fixed'),
                ...$repConfig->getFields(),
            ]),
        ];
    }
}
