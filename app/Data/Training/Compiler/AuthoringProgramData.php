<?php

namespace App\Data\Training\Compiler;

final readonly class AuthoringProgramData
{
    /**
     * @param  AuthoringExerciseData[]  $exercises
     */
    public function __construct(
        public array $exercises,
    ) {}
}
