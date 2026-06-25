<?php

use App\Support\AthleteDashboardDate;
use Carbon\CarbonImmutable;

it('uses the built-in feature defaults when can_edit_all and granular overrides are unset', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', null);
    config()->set('athlete.edit_future', null);

    expect(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-02'))->toBeTrue()
        ->and(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-03'))->toBeTrue()
        ->and(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-04'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-02'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-03'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-04'))->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('uses the configured athlete future editing policy for date-sensitive athlete edits', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', null);
    config()->set('athlete.edit_future', false);

    expect(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-02'))->toBeTrue()
        ->and(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-03'))->toBeTrue()
        ->and(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-04'))->toBeFalse()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-02'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-03'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-04'))->toBeFalse();

    config()->set('athlete.edit_future', true);

    expect(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-04'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-04'))->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('uses can_edit_all as the default athlete editability policy', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', false);

    expect(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-03'))->toBeFalse()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-02'))->toBeFalse()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-04'))->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('lets granular editability override can_edit_all per feature and date relation', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', false);
    config()->set('athlete.editability.readiness.future', true);
    config()->set('athlete.editability.programs.exercises.today', true);

    expect(AthleteDashboardDate::canSubmitReadinessForDate('2030-04-04'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-03'))->toBeTrue()
        ->and(AthleteDashboardDate::canRecordProgramExercisesForDate('2030-04-02'))->toBeFalse();

    CarbonImmutable::setTestNow();
});
