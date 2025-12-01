<?php

use App\Models\Training\Progression\Config\ExerciseConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Override\SetOverride;
use App\Models\Training\Progression\Override\WeekAnchorOverride;
use App\Models\Training\Progression\Result\ExerciseProgression;
use App\Models\Training\Progression\Strategy\ProgressionCalculator;
use Tests\Feature\Training\Progression\ProgressionTestCase;

uses(ProgressionTestCase::class);

describe('ProgressionCalculator', function () {
    beforeEach(function () {
        $this->tree = $this->createTestTree();
        $this->sessionMap = $this->createSessionMap($this->tree);
        $this->config = $this->createDefaultConfig();
        $this->athlete = $this->createDefaultAthleteData();
        $this->overrides = $this->createDefaultOverrideStore();
    });

    describe('basic calculation', function () {
        it('calculates exercise progression', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression)->toBeInstanceOf(ExerciseProgression::class);
            expect($progression->exerciseId)->toBe(1);
            expect($progression->derived1RM)->toBe(70.0);
            expect($progression->target1RM)->toBe(78.75);
            expect($progression->weeks)->toHaveCount(5);
        });

        it('calculates all weeks with sessions', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            foreach ($progression->weeks as $week) {
                expect($week->sessions)->not->toBeEmpty();
                foreach ($week->sessions as $session) {
                    expect($session->sets)->not->toBeEmpty();
                }
            }
        });

        it('calculates anchor weights increasing across weeks', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            $anchorWeights = array_map(fn ($week) => $week->anchorWeight, $progression->weeks);

            expect($anchorWeights[0])->toBeLessThan($anchorWeights[4]);
        });

        it('calculates anchor reps decreasing across weeks', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[0]->anchorReps)->toBe(10);
            expect($progression->weeks[4]->anchorReps)->toBe(6);
        });

        it('all sets have computed source by default', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            foreach ($progression->weeks as $week) {
                foreach ($week->sessions as $session) {
                    foreach ($session->sets as $set) {
                        expect($set->source)->toBe('computed');
                    }
                }
            }
        });
    });

    describe('with fixed_step weight strategy', function () {
        it('calculates weights working backwards from target', function () {
            $this->config->weightStrategy = 'fixed_step';
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[4]->anchorWeight)->toBeGreaterThan(66.5)->toBeLessThan(68.5);
        });
    });

    describe('with compounded weight strategy', function () {
        it('calculates weights using compound growth', function () {
            $this->config->weightStrategy = 'compounded';
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[0]->anchorWeight)->toBeGreaterThan(51.0)->toBeLessThan(53.0);
            expect($progression->weeks[4]->anchorWeight)->toBeGreaterThan(66.5)->toBeLessThan(68.5);
        });

        it('sets projected 1RM for each week', function () {
            $this->config->weightStrategy = 'compounded';
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[0]->projected1RM)->toBe(70.0);
            expect($progression->weeks[4]->projected1RM)->toBeGreaterThan(78.6)->toBeLessThan(78.9);
        });
    });

    describe('with paired_ladder rep strategy', function () {
        it('calculates reps in pairs', function () {
            $this->config->repStrategy = 'paired_ladder';
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            $sessionUuid = array_key_first($this->sessionMap[1]);
            $week1Session = $progression->weeks[1]->getSession($sessionUuid);
            expect($week1Session->sets[0]->reps)->toBe($week1Session->sets[1]->reps);
        });
    });

    describe('with proportional rep strategy', function () {
        it('calculates reps based on weight percentage', function () {
            $this->config->repStrategy = 'proportional';
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[0]->reference1RM)->toBe(70.0);
        });
    });

    describe('with exercise config overrides', function () {
        it('applies exercise modifier to derived 1RM', function () {
            $this->config->exerciseOverrides[1] = new ExerciseConfig(
                exerciseId: 1,
                modifier: 0.85,
            );
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->derived1RM)->toBe(59.5);
        });

        it('applies per-exercise weight strategy', function () {
            $this->config->exerciseOverrides[1] = new ExerciseConfig(
                exerciseId: 1,
                weightStrategy: 'compounded',
            );
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->config->weightStrategy)->toBe('compounded');
        });
    });

    describe('with set overrides', function () {
        it('applies manual set override', function () {
            $sessionUuid = array_key_first($this->sessionMap[0]);
            $key = OverrideStore::buildSetKey(1, 0, $sessionUuid, 0);
            $this->overrides->setSetOverride($key, new SetOverride(reps: 10, weight: 50.0));

            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);
            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            $set = $progression->weeks[0]->getSession($sessionUuid)->sets[0];
            expect($set->reps)->toBe(10);
            expect($set->weight)->toBe(50.0);
            expect($set->source)->toBe('manual');
            expect($set->computedReps)->not->toBeNull();
            expect($set->computedWeight)->not->toBeNull();
        });

        it('applies partial set override (reps only)', function () {
            $sessionUuid = array_key_first($this->sessionMap[0]);
            $key = OverrideStore::buildSetKey(1, 0, $sessionUuid, 0);
            $this->overrides->setSetOverride($key, new SetOverride(reps: 10));

            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);
            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            $set = $progression->weeks[0]->getSession($sessionUuid)->sets[0];
            expect($set->reps)->toBe(10);
            expect($set->source)->toBe('manual');
        });

        it('applies locked set override', function () {
            $sessionUuid = array_key_first($this->sessionMap[0]);
            $key = OverrideStore::buildSetKey(1, 0, $sessionUuid, 0);
            $this->overrides->setSetOverride($key, new SetOverride(reps: 12, weight: 45.0, locked: true));

            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);
            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            $set = $progression->weeks[0]->getSession($sessionUuid)->sets[0];
            expect($set->source)->toBe('locked');
        });
    });

    describe('with week anchor overrides', function () {
        it('applies single week anchor override', function () {
            $key = OverrideStore::buildWeekKey(1, 2);
            $this->overrides->setWeekAnchorOverride($key, new WeekAnchorOverride(
                value: 60.0,
                scope: 'single',
                fromWeek: 2,
            ));

            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);
            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[2]->anchorWeight)->toBe(60.0);
            expect($progression->weeks[1]->anchorWeight)->not->toBe(60.0);
            expect($progression->weeks[3]->anchorWeight)->not->toBe(60.0);
        });

        it('applies carry forward anchor override', function () {
            $key = OverrideStore::buildWeekKey(1, 2);
            $this->overrides->setWeekAnchorOverride($key, new WeekAnchorOverride(
                value: 55.0,
                scope: 'carry_forward',
                fromWeek: 2,
            ));

            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);
            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->weeks[2]->anchorWeight)->toBe(55.0);
            expect($progression->weeks[3]->anchorWeight)->toBe(55.0);
            expect($progression->weeks[4]->anchorWeight)->toBe(55.0);
        });
    });

    describe('set override methods', function () {
        it('can set override through calculator', function () {
            $sessionUuid = array_key_first($this->sessionMap[0]);
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $calculator->setSetOverride(1, 0, $sessionUuid, 0, 10, 50.0);

            $key = OverrideStore::buildSetKey(1, 0, $sessionUuid, 0);
            $override = $this->overrides->getSetOverride($key);
            expect($override->reps)->toBe(10);
            expect($override->weight)->toBe(50.0);
        });

        it('can clear override through calculator', function () {
            $sessionUuid = array_key_first($this->sessionMap[0]);
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            $calculator->setSetOverride(1, 0, $sessionUuid, 0, 10, 50.0);
            $calculator->clearSetOverride(1, 0, $sessionUuid, 0);

            $key = OverrideStore::buildSetKey(1, 0, $sessionUuid, 0);
            expect($this->overrides->getSetOverride($key))->toBeNull();
        });
    });

    describe('accessors', function () {
        it('returns override store', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            expect($calculator->getOverrideStore())->toBe($this->overrides);
        });

        it('returns global config', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides, $this->athlete);

            expect($calculator->getGlobalConfig())->toBe($this->config);
        });
    });

    describe('without athlete data', function () {
        it('uses default derived 1RM when no athlete', function () {
            $calculator = new ProgressionCalculator($this->config, $this->overrides);

            $progression = $calculator->calculateExerciseProgression(1, $this->sessionMap);

            expect($progression->derived1RM)->toBe(70.0);
        });
    });
});
