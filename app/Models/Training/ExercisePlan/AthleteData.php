<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class AthleteData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public string $forename,
        public string $surname,
        #[DataCollectionOf(AthleteTestData::class)]
        public array $tests = [],
        public float $target_modifier = 100.0,

    ) {}

    public function name(): string
    {
        return trim("{$this->forename} {$this->surname}");
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            forename: $user->forename ?? '',
            surname: $user->surname ?? '',
            tests: [
                AthleteTestData::back_squat(
                    reps: (int) ($user->extra['test_reps'] ?? 1),
                    weight: (float) ($user->extra['test_weight'] ?? 50.0),
                ),
            ],
            target_modifier: (float) ($user->extra['target_modifier'] ?? 100.0),
        );
    }

    public function persist(): void
    {
        $test = $this->tests[0] ?? null;
        $extra = [
            'test_reps' => $test?->reps ?? 1,
            'test_weight' => $test?->weight ?? 50.0,
            'target_modifier' => $this->target_modifier,
        ];

        if ($this->id === null) {
            $user = User::create([
                'forename' => $this->forename,
                'surname' => $this->surname,
                'type' => UserTypeEnum::Athlete,
                'extra' => $extra,
            ]);
            $this->id = $user->id;
        } else {
            $user = User::findOrFail($this->id);
            $user->forename = $this->forename;
            $user->surname = $this->surname;
            $user->extra = $extra;
            $user->save();
        }
    }

    public static function example($id = 1, $forename = 'John', $surname = 'Doe', $reps = 8, $weight = 45): self
    {
        return new self(
            id: $id,
            forename: $forename,
            surname: $surname,
            tests: [
                AthleteTestData::back_squat($reps, $weight),
            ],
        );
    }

    public function getOneRepMaxForExercise(ExerciseData $exercise): float
    {
        foreach ($this->tests as $test) {
            if ($test->exerciseId === $exercise->id) {
                return $test->oneRepMax;
            }
        }

        foreach ($this->tests as $test) {
            if ($test->exerciseId === 1) {
                return $test->oneRepMax * ($exercise->modifier / 100);
            }
        }

        return 0.0;
    }
}
