<?php

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Settings\RepsSetting;

describe('parse', function () {
    it('parses plain integer', function () {
        $reps = BilateralReps::parse(10);

        expect($reps->left)->toBe(10);
        expect($reps->right)->toBeNull();
        expect($reps->isBilateral())->toBeFalse();
    });

    it('parses numeric string', function () {
        $reps = BilateralReps::parse('15');

        expect($reps->left)->toBe(15);
        expect($reps->right)->toBeNull();
        expect($reps->isBilateral())->toBeFalse();
    });

    it('parses bilateral string with equal sides', function () {
        $reps = BilateralReps::parse('15_15');

        expect($reps->left)->toBe(15);
        expect($reps->right)->toBe(15);
        expect($reps->isBilateral())->toBeTrue();
    });

    it('parses bilateral string with different sides', function () {
        $reps = BilateralReps::parse('12_15');

        expect($reps->left)->toBe(12);
        expect($reps->right)->toBe(15);
        expect($reps->isBilateral())->toBeTrue();
    });

    it('handles trimmed whitespace', function () {
        $reps = BilateralReps::parse(' 10_12 ');

        expect($reps->left)->toBe(10);
        expect($reps->right)->toBe(12);
    });
});

describe('total', function () {
    it('returns left for unilateral', function () {
        expect(BilateralReps::parse(15)->total())->toBe(15);
        expect(BilateralReps::parse('8')->total())->toBe(8);
    });

    it('returns sum for bilateral', function () {
        expect(BilateralReps::parse('15_15')->total())->toBe(30);
        expect(BilateralReps::parse('12_15')->total())->toBe(27);
    });
});

describe('decrement', function () {
    it('decrements unilateral', function () {
        $reps = BilateralReps::parse(10)->decrement(2);

        expect($reps->left)->toBe(8);
        expect($reps->right)->toBeNull();
    });

    it('decrements both sides of bilateral', function () {
        $reps = BilateralReps::parse('15_15')->decrement(2);

        expect($reps->left)->toBe(13);
        expect($reps->right)->toBe(13);
        expect((string) $reps)->toBe('13_13');
    });

    it('decrements asymmetric bilateral', function () {
        $reps = BilateralReps::parse('12_15')->decrement(2);

        expect($reps->left)->toBe(10);
        expect($reps->right)->toBe(13);
    });
});

describe('increment', function () {
    it('increments unilateral', function () {
        $reps = BilateralReps::parse(8)->increment(2);

        expect($reps->left)->toBe(10);
        expect($reps->right)->toBeNull();
    });

    it('increments both sides of bilateral', function () {
        $reps = BilateralReps::parse('13_13')->increment(2);

        expect($reps->left)->toBe(15);
        expect($reps->right)->toBe(15);
        expect((string) $reps)->toBe('15_15');
    });
});

describe('clampMinimum', function () {
    it('clamps unilateral to minimum', function () {
        $reps = BilateralReps::parse(0)->clampMinimum(1);

        expect($reps->left)->toBe(1);
    });

    it('does not clamp when above minimum', function () {
        $reps = BilateralReps::parse(10)->clampMinimum(1);

        expect($reps->left)->toBe(10);
    });

    it('clamps each side of bilateral independently', function () {
        $reps = BilateralReps::parse('0_5')->clampMinimum(3);

        expect($reps->left)->toBe(3);
        expect($reps->right)->toBe(5);
    });

    it('clamps both sides of bilateral', function () {
        $reps = BilateralReps::parse('1_1')->clampMinimum(3);

        expect($reps->left)->toBe(3);
        expect($reps->right)->toBe(3);
    });
});

describe('__toString', function () {
    it('returns number string for unilateral', function () {
        expect((string) BilateralReps::parse(15))->toBe('15');
        expect((string) BilateralReps::parse('8'))->toBe('8');
    });

    it('returns underscore format for bilateral', function () {
        expect((string) BilateralReps::parse('15_15'))->toBe('15_15');
        expect((string) BilateralReps::parse('12_15'))->toBe('12_15');
    });
});

describe('isValid', function () {
    it('validates plain numbers', function () {
        expect(BilateralReps::isValid('15'))->toBeTrue();
        expect(BilateralReps::isValid('1'))->toBeTrue();
        expect(BilateralReps::isValid('100'))->toBeTrue();
    });

    it('validates bilateral format', function () {
        expect(BilateralReps::isValid('15_15'))->toBeTrue();
        expect(BilateralReps::isValid('12_15'))->toBeTrue();
        expect(BilateralReps::isValid('1_1'))->toBeTrue();
    });

    it('rejects invalid formats', function () {
        expect(BilateralReps::isValid('abc'))->toBeFalse();
        expect(BilateralReps::isValid('15_'))->toBeFalse();
        expect(BilateralReps::isValid('_15'))->toBeFalse();
        expect(BilateralReps::isValid(''))->toBeFalse();
        expect(BilateralReps::isValid('15_15_15'))->toBeFalse();
        expect(BilateralReps::isValid('15.5'))->toBeFalse();
    });
});

describe('athlete canonical value', function () {
    it('formats split reps with side labels for display', function () {
        expect(RepsSetting::formatAthleteValue('6_6'))->toBe('6L_6R')
            ->and(RepsSetting::formatAthleteValue('8'))->toBe('8')
            ->and(RepsSetting::formatAthleteValue('8-10'))->toBe('8-10');
    });

    it('infers alternating bilateral execution metadata for split reps', function () {
        expect(RepsSetting::athleteCanonicalValue('6_6'))->toMatchArray([
            'kind' => 'reps',
            'format' => 'split',
            'display' => '6L_6R',
            'total' => 12,
            'parts' => [6, 6],
            'is_bilateral' => true,
            'bilateral_execution' => RepsSetting::BILATERAL_EXECUTION_ALTERNATING,
        ]);
    });

    it('ignores legacy bilateral execution config for split reps', function () {
        expect(RepsSetting::athleteCanonicalValue('6_6', [
            'bilateralExecution' => RepsSetting::BILATERAL_EXECUTION_CONSECUTIVE,
        ]))->toMatchArray([
            'is_bilateral' => true,
            'bilateral_execution' => RepsSetting::BILATERAL_EXECUTION_ALTERNATING,
        ]);
    });

    it('does not include bilateral execution metadata for scalar reps', function () {
        expect(RepsSetting::athleteCanonicalValue(6))->toMatchArray([
            'is_bilateral' => false,
            'bilateral_execution' => null,
        ]);
    });
});

describe('reps setting form fields', function () {
    it('does not expose bilateral order as a separate setting', function () {
        expect(collect(RepsSetting::fields())->firstWhere('name', 'bilateralExecution'))->toBeNull();
    });

    it('updates default reps live so dependent settings can react', function () {
        $field = collect(RepsSetting::fields())->firstWhere('name', 'default');

        expect($field?->live)->toBeTrue();
    });
});
