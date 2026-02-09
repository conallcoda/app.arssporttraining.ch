<?php

namespace App\Data\Exercise;

use App\Training\Handlers\CardioPlanHandler;
use App\Training\Handlers\ConditioningPlanHandler;
use App\Training\Handlers\ExercisePlanHandlerInterface;
use App\Training\Handlers\StrengthAutomaticPlanHandler;
use App\Training\Handlers\StrengthManualPlanHandler;
use App\Training\Handlers\TimedPlanHandler;

enum ExerciseType: string
{
    case StrengthAutomatic = 'strength_automatic';
    case StrengthManual = 'strength_manual';
    case Conditioning = 'conditioning';
    case Timed = 'timed';
    case Cardio = 'cardio';

    public function label(): string
    {
        return match ($this) {
            self::StrengthAutomatic => 'Strength (Automatic)',
            self::StrengthManual => 'Strength (Manual)',
            self::Conditioning => 'Conditioning',
            self::Timed => 'Timed',
            self::Cardio => 'Cardio',
        };
    }

    public function getConfigClass(): string
    {
        return match ($this) {
            self::StrengthAutomatic => Types\StrengthAutomaticExerciseConfig::class,
            self::StrengthManual => Types\StrengthManualExerciseConfig::class,
            self::Conditioning => Types\ConditioningExerciseConfig::class,
            self::Timed => Types\TimedExerciseConfig::class,
            self::Cardio => Types\CardioExerciseConfig::class,
        };
    }

    public function getConfigAccessor(): string
    {
        return match ($this) {
            self::StrengthAutomatic => 'strength_automatic',
            self::StrengthManual => 'strength_manual',
            self::Conditioning => 'conditioning',
            self::Timed => 'timed',
            self::Cardio => 'cardio',
        };
    }

    public function getFields(array $data = []): array
    {
        return $this->getConfigClass()::getFields($data);
    }

    public function getPlanHandler(): ExercisePlanHandlerInterface
    {
        return match ($this) {
            self::StrengthAutomatic => new StrengthAutomaticPlanHandler,
            self::StrengthManual => new StrengthManualPlanHandler,
            self::Conditioning => new ConditioningPlanHandler,
            self::Timed => new TimedPlanHandler,
            self::Cardio => new CardioPlanHandler,
        };
    }
}
