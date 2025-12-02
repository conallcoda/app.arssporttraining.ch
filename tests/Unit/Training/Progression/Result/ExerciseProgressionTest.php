<?php

use App\Models\Training\Progression\Result\ExerciseProgression;
use App\Models\Training\Progression\Result\SessionResult;
use App\Models\Training\Progression\Result\SetResult;
use App\Models\Training\Progression\Result\WeekResult;
use Tests\Unit\Training\Progression\ProgressionTestHelpers;

uses(ProgressionTestHelpers::class);

describe('ExerciseProgression', function () {
    beforeEach(function () {
        $this->config = $this->createDefaultResolvedConfig();

        $this->weeks = [];
        for ($i = 0; $i < 5; $i++) {
            $this->weeks[] = new WeekResult(
                weekIndex: $i,
                anchorWeight: 47.5 + ($i * 5),
                anchorReps: $this->config->getAnchorRepsForWeek($i),
                sessions: [
                    new SessionResult(
                        sessionUuid: "uuid-{$i}",
                        day: 0,
                        slot: 0,
                        sets: [
                            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
                            new SetResult(setIndex: 1, reps: 12, weight: 45.0, source: 'computed'),
                            new SetResult(setIndex: 2, reps: 10, weight: 47.5, source: 'computed'),
                        ],
                    ),
                ],
            );
        }
    });

    it('can be created with weeks', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        expect($progression->exerciseId)->toBe(1);
        expect($progression->derived1RM)->toBe(70.0);
        expect($progression->target1RM)->toBe(78.75);
        expect($progression->config)->toBe($this->config);
        expect($progression->weeks)->toHaveCount(5);
    });

    it('can get week by index', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        expect($progression->getWeek(0)->weekIndex)->toBe(0);
        expect($progression->getWeek(4)->weekIndex)->toBe(4);
        expect($progression->getWeek(10))->toBeNull();
    });

    it('returns week count', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        expect($progression->weekCount())->toBe(5);
    });

    it('returns improvement percentage', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        expect($progression->getImprovementPercentage())->toBe(12.5);
    });

    it('counts completed weeks', function () {
        $lockedWeeks = [];
        for ($i = 0; $i < 3; $i++) {
            $lockedWeeks[] = new WeekResult(
                weekIndex: $i,
                anchorWeight: 47.5 + ($i * 5),
                anchorReps: 10,
                sessions: [
                    new SessionResult(
                        sessionUuid: "uuid-{$i}",
                        day: 0,
                        slot: 0,
                        sets: [
                            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
                            new SetResult(setIndex: 1, reps: 10, weight: 47.5, source: 'locked'),
                        ],
                    ),
                ],
            );
        }

        for ($i = 3; $i < 5; $i++) {
            $lockedWeeks[] = new WeekResult(
                weekIndex: $i,
                anchorWeight: 47.5 + ($i * 5),
                anchorReps: 10,
                sessions: [
                    new SessionResult(
                        sessionUuid: "uuid-{$i}",
                        day: 0,
                        slot: 0,
                        sets: [
                            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
                            new SetResult(setIndex: 1, reps: 10, weight: 47.5, source: 'computed'),
                        ],
                    ),
                ],
            );
        }

        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $lockedWeeks,
        );

        expect($progression->getCompletedWeekCount())->toBe(3);
    });

    it('calculates progress percentage', function () {
        $lockedWeeks = [];
        for ($i = 0; $i < 2; $i++) {
            $lockedWeeks[] = new WeekResult(
                weekIndex: $i,
                anchorWeight: 47.5,
                anchorReps: 10,
                sessions: [
                    new SessionResult(
                        sessionUuid: "uuid-{$i}",
                        day: 0,
                        slot: 0,
                        sets: [
                            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'locked'),
                        ],
                    ),
                ],
            );
        }

        for ($i = 2; $i < 5; $i++) {
            $lockedWeeks[] = new WeekResult(
                weekIndex: $i,
                anchorWeight: 47.5,
                anchorReps: 10,
                sessions: [
                    new SessionResult(
                        sessionUuid: "uuid-{$i}",
                        day: 0,
                        slot: 0,
                        sets: [
                            new SetResult(setIndex: 0, reps: 12, weight: 42.5, source: 'computed'),
                        ],
                    ),
                ],
            );
        }

        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $lockedWeeks,
        );

        expect($progression->getProgressPercentage())->toBe(40.0);
    });

    it('calculates total volume across all weeks', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        $sessionVolume = (12 * 42.5) + (12 * 45.0) + (10 * 47.5);
        $expectedVolume = $sessionVolume * 5;
        expect($progression->getTotalVolume())->toBe($expectedVolume);
    });

    it('returns anchor progression', function () {
        $progression = new ExerciseProgression(
            exerciseId: 1,
            derived1RM: 70.0,
            target1RM: 78.75,
            config: $this->config,
            weeks: $this->weeks,
        );

        $anchorProgression = $progression->getAnchorProgression();

        expect($anchorProgression)->toHaveCount(5);
        expect($anchorProgression[0]['week'])->toBe(1);
        expect($anchorProgression[0]['weight'])->toBe(47.5);
        expect($anchorProgression[4]['week'])->toBe(5);
        expect($anchorProgression[4]['weight'])->toBe(67.5);
    });
});
