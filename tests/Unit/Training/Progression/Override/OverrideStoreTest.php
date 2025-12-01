<?php

use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Override\SetOverride;
use App\Models\Training\Progression\Override\WeekAnchorOverride;

describe('OverrideStore', function () {
    it('can be created empty', function () {
        $store = new OverrideStore;

        expect($store->setOverrides)->toBe([]);
        expect($store->weekAnchorOverrides)->toBe([]);
        expect($store->impliedValues)->toBe([]);
    });

    describe('key building', function () {
        it('builds set key correctly', function () {
            $key = OverrideStore::buildSetKey(1, 2, 'session-uuid', 3);

            expect($key)->toBe('exercise:1:week:2:session:session-uuid:set:3');
        });

        it('builds week key correctly', function () {
            $key = OverrideStore::buildWeekKey(1, 2);

            expect($key)->toBe('exercise:1:week:2');
        });

        it('builds session key correctly', function () {
            $key = OverrideStore::buildSessionKey(1, 2, 'session-uuid');

            expect($key)->toBe('exercise:1:week:2:session:session-uuid');
        });
    });

    describe('set overrides', function () {
        it('can set and get set override', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildSetKey(1, 0, 'uuid', 0);
            $override = new SetOverride(reps: 10, weight: 55.0);

            $store->setSetOverride($key, $override);

            expect($store->getSetOverride($key))->toBe($override);
        });

        it('returns null for non-existent set override', function () {
            $store = new OverrideStore;

            expect($store->getSetOverride('non-existent'))->toBeNull();
        });

        it('can remove set override', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildSetKey(1, 0, 'uuid', 0);
            $store->setSetOverride($key, new SetOverride(reps: 10));

            $store->removeSetOverride($key);

            expect($store->getSetOverride($key))->toBeNull();
        });

        it('can check if set is locked', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildSetKey(1, 0, 'uuid', 0);

            expect($store->isLocked($key))->toBeFalse();

            $store->setSetOverride($key, new SetOverride(locked: true));

            expect($store->isLocked($key))->toBeTrue();
        });

        it('can lock a set', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildSetKey(1, 0, 'uuid', 0);

            $store->lockSet($key, 10, 55.0);

            $override = $store->getSetOverride($key);
            expect($override->locked)->toBeTrue();
            expect($override->reps)->toBe(10);
            expect($override->weight)->toBe(55.0);
            expect($override->lockedAt)->not->toBeNull();
        });

        it('can lock entire session', function () {
            $store = new OverrideStore;

            $store->lockSession(1, 0, 'uuid', [
                0 => ['reps' => 12, 'weight' => 47.5],
                1 => ['reps' => 12, 'weight' => 50.0],
                2 => ['reps' => 10, 'weight' => 52.5],
            ]);

            $key0 = OverrideStore::buildSetKey(1, 0, 'uuid', 0);
            $key1 = OverrideStore::buildSetKey(1, 0, 'uuid', 1);
            $key2 = OverrideStore::buildSetKey(1, 0, 'uuid', 2);

            expect($store->isLocked($key0))->toBeTrue();
            expect($store->isLocked($key1))->toBeTrue();
            expect($store->isLocked($key2))->toBeTrue();

            expect($store->getSetOverride($key0)->reps)->toBe(12);
            expect($store->getSetOverride($key2)->weight)->toBe(52.5);
        });
    });

    describe('week anchor overrides', function () {
        it('can set and get week anchor override', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildWeekKey(1, 2);
            $override = new WeekAnchorOverride(value: 60.0);

            $store->setWeekAnchorOverride($key, $override);

            expect($store->getWeekAnchorOverride($key))->toBe($override);
        });

        it('returns null for non-existent week anchor override', function () {
            $store = new OverrideStore;

            expect($store->getWeekAnchorOverride('non-existent'))->toBeNull();
        });

        it('can remove week anchor override', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildWeekKey(1, 2);
            $store->setWeekAnchorOverride($key, new WeekAnchorOverride(value: 60.0));

            $store->removeWeekAnchorOverride($key);

            expect($store->getWeekAnchorOverride($key))->toBeNull();
        });

        it('finds applicable anchor override for exact week', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildWeekKey(1, 2);
            $override = new WeekAnchorOverride(value: 60.0, scope: 'single', fromWeek: 2);
            $store->setWeekAnchorOverride($key, $override);

            expect($store->findApplicableAnchorOverride(1, 2))->toBe($override);
            expect($store->findApplicableAnchorOverride(1, 3))->toBeNull();
        });

        it('finds applicable carry forward anchor override', function () {
            $store = new OverrideStore;
            $key = OverrideStore::buildWeekKey(1, 2);
            $override = new WeekAnchorOverride(value: 60.0, scope: 'carry_forward', fromWeek: 2);
            $store->setWeekAnchorOverride($key, $override);

            expect($store->findApplicableAnchorOverride(1, 1))->toBeNull();
            expect($store->findApplicableAnchorOverride(1, 2))->toBe($override);
            expect($store->findApplicableAnchorOverride(1, 3))->toBe($override);
            expect($store->findApplicableAnchorOverride(1, 4))->toBe($override);
        });

        it('finds most recent applicable carry forward override', function () {
            $store = new OverrideStore;

            $override1 = new WeekAnchorOverride(value: 55.0, scope: 'carry_forward', fromWeek: 1);
            $override3 = new WeekAnchorOverride(value: 65.0, scope: 'carry_forward', fromWeek: 3);

            $store->setWeekAnchorOverride(OverrideStore::buildWeekKey(1, 1), $override1);
            $store->setWeekAnchorOverride(OverrideStore::buildWeekKey(1, 3), $override3);

            expect($store->findApplicableAnchorOverride(1, 0))->toBeNull();
            expect($store->findApplicableAnchorOverride(1, 1))->toBe($override1);
            expect($store->findApplicableAnchorOverride(1, 2))->toBe($override1);
            expect($store->findApplicableAnchorOverride(1, 3))->toBe($override3);
            expect($store->findApplicableAnchorOverride(1, 4))->toBe($override3);
        });

        it('does not mix overrides between exercises', function () {
            $store = new OverrideStore;

            $override1 = new WeekAnchorOverride(value: 55.0, scope: 'carry_forward', fromWeek: 0);
            $store->setWeekAnchorOverride(OverrideStore::buildWeekKey(1, 0), $override1);

            expect($store->findApplicableAnchorOverride(1, 2))->toBe($override1);
            expect($store->findApplicableAnchorOverride(2, 2))->toBeNull();
        });
    });

    describe('implied values', function () {
        it('can set and get implied value', function () {
            $store = new OverrideStore;

            $store->setImpliedValue('exercise:1:implied1rm', 75.0);

            expect($store->getImpliedValue('exercise:1:implied1rm'))->toBe(75.0);
        });

        it('returns null for non-existent implied value', function () {
            $store = new OverrideStore;

            expect($store->getImpliedValue('non-existent'))->toBeNull();
        });
    });

    describe('clearing overrides', function () {
        it('can clear all overrides for an exercise', function () {
            $store = new OverrideStore;

            $store->setSetOverride(OverrideStore::buildSetKey(1, 0, 'uuid', 0), new SetOverride(reps: 10));
            $store->setSetOverride(OverrideStore::buildSetKey(1, 1, 'uuid', 0), new SetOverride(reps: 10));
            $store->setSetOverride(OverrideStore::buildSetKey(2, 0, 'uuid', 0), new SetOverride(reps: 10));
            $store->setWeekAnchorOverride(OverrideStore::buildWeekKey(1, 2), new WeekAnchorOverride(value: 60.0));
            $store->setImpliedValue('exercise:1:implied1rm', 75.0);

            $store->clearExerciseOverrides(1);

            expect($store->getSetOverride(OverrideStore::buildSetKey(1, 0, 'uuid', 0)))->toBeNull();
            expect($store->getSetOverride(OverrideStore::buildSetKey(1, 1, 'uuid', 0)))->toBeNull();
            expect($store->getSetOverride(OverrideStore::buildSetKey(2, 0, 'uuid', 0)))->not->toBeNull();
            expect($store->getWeekAnchorOverride(OverrideStore::buildWeekKey(1, 2)))->toBeNull();
            expect($store->getImpliedValue('exercise:1:implied1rm'))->toBeNull();
        });
    });
});
