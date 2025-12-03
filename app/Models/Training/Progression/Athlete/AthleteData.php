<?php

namespace App\Models\Training\Progression\Athlete;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Users\Types\Athlete;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class AthleteData extends AbstractData
{
    public function __construct(
        public int $athleteId,
        #[DataCollectionOf(AthleteTest::class)]
        public array $tests = [],
    ) {}

    public function getTest(int $exerciseId): ?AthleteTest
    {
        return $this->tests[$exerciseId] ?? null;
    }

    public function setTest(AthleteTest $test): static
    {
        $this->tests[$test->exerciseId] = $test;

        return $this;
    }

    public function getDerived1RM(int $exerciseId, float $modifier = 1.0): ?float
    {
        $test = $this->getTest($exerciseId);

        if ($test === null) {
            return null;
        }

        return $test->getDerived1RM() * $modifier;
    }

    public function hasTest(int $exerciseId): bool
    {
        return isset($this->tests[$exerciseId]);
    }

    public static function getFields(): array
    {
        return [
            FluxField::select('athleteId')
                ->label('Athlete')
                ->options(Athlete::orderBy('forename')->orderBy('surname')->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->required()
                ->rules('required|integer|exists:users,id'),
            FluxField::repeater('tests')
                ->label('Test Results')
                ->schema(AthleteTest::getFields()),
        ];
    }
}
