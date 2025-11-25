<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;

class SeasonConfig extends AbstractData
{
    public function __construct(
        public string $name = 'New Season',
        public int $numberOfBlocks = 2,
        public int $weeksPerBlock = 5,
        public int $sessionsPerWeek = 0,
        public int $exercisesPerSession = 0,
        public int $setsPerExercise = 0,
        public ?int $defaultCategoryId = null,
        public ?array $exerciseIds = null,
    ) {}
}
