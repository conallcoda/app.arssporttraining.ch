<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseWeek extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(ExerciseSession::class)]
        public array $sessions = [],
    ) {}

    public static function example(): self
    {
        return new self(
            sessions: [
                ExerciseSession::example(),
                ExerciseSession::example(),
            ],
        );
    }
}
