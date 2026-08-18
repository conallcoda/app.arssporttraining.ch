<?php

use App\Livewire\Database\AthleteMetricFormModal;
use App\Livewire\Database\ExerciseForm;
use App\Livewire\Database\ExerciseTemplateForm;
use App\Livewire\Training\BlockForm;
use App\Livewire\Training\CalendarExerciseSettingsForm;
use App\Livewire\Training\ExerciseProgramFormModal;
use App\Livewire\Training\View\PlanExerciseSettingsForm;
use App\Livewire\Training\WeekSlotForm;
use Coda\Cms\Livewire\FormModal;

it('keeps form modal open overrides compatible with the shared CMS', function () {
    $formModals = [
        AthleteMetricFormModal::class,
        BlockForm::class,
        CalendarExerciseSettingsForm::class,
        ExerciseForm::class,
        ExerciseProgramFormModal::class,
        ExerciseTemplateForm::class,
        PlanExerciseSettingsForm::class,
        WeekSlotForm::class,
    ];

    $parentParameters = array_map(
        fn (ReflectionParameter $parameter): string => $parameter->getName(),
        (new ReflectionMethod(FormModal::class, 'open'))->getParameters(),
    );

    foreach ($formModals as $formModal) {
        $parameters = array_map(
            fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod($formModal, 'open'))->getParameters(),
        );

        expect($parameters)->toBe($parentParameters, "{$formModal}::open() must match FormModal::open()");
    }
});
