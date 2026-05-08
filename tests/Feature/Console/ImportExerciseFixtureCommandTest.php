<?php

use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-04 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('resets Conall fixture data and creates the representative exercise bundle', function () {
    $envPath = base_path('tests/Fixtures/tmp.exercise-fixture.env');
    File::ensureDirectoryExists(dirname($envPath));
    File::put($envPath, "APP_ENV=testing\n");
    config()->set('app.test_user_env_file', $envPath);

    $this->artisan('db:import-exercise-fixture')->assertExitCode(0);

    expect(Tag::count())->toBe(14)
        ->and(ExerciseTemplate::count())->toBe(2)
        ->and(Exercise::count())->toBe(24)
        ->and(User::count())->toBe(4)
        ->and(UserGroup::count())->toBe(1)
        ->and(ExerciseProgram::count())->toBe(5)
        ->and(TrainingProgram::count())->toBe(5)
        ->and(TrainingProgramBlock::count())->toBe(5)
        ->and(TrainingProgramSlot::count())->toBe(162)
        ->and(MetricSubmission::count())->toBe(7);

    $coach = User::query()->where('email', 'conall@coda.works')->firstOrFail();

    expect($coach->type->value)->toBe('coach')
        ->and($coach->forename)->toBe('Conall')
        ->and($coach->surname)->toBe("O'Reilly")
        ->and($coach->email)->toBe('conall@coda.works')
        ->and(Hash::check('Crepeface1', (string) $coach->password))->toBeTrue();

    expect(File::get($envPath))
        ->toContain('TEST_USER_EMAIL=conall@coda.works')
        ->toContain('TEST_USER_PASSWORD=Crepeface1');

    $athletes = User::query()
        ->where('type', 'athlete')
        ->orderBy('id')
        ->get(['owner_id', 'forename', 'surname'])
        ->map(fn (User $user) => [
            'owner_id' => $user->owner_id,
            'forename' => $user->forename,
            'surname' => $user->surname,
        ])
        ->all();

    expect($athletes)->toBe([
        ['owner_id' => $coach->id, 'forename' => 'John', 'surname' => 'Doe'],
        ['owner_id' => $coach->id, 'forename' => 'Max', 'surname' => 'Mustermann'],
        ['owner_id' => $coach->id, 'forename' => 'Joe', 'surname' => 'Bloggs'],
    ]);

    $group = UserGroup::query()->where('name', "Conall's Test Group")->firstOrFail();

    expect($group->owner_id)->toBe($coach->id)
        ->and($group->members()->count())->toBe(3);

    $strengthTrainingProgram = TrainingProgram::query()
        ->where('owner_id', $coach->id)
        ->whereHas('program', fn ($query) => $query->where('name', 'Strength - Test Program'))
        ->firstOrFail();

    $backSquat = Exercise::query()->where('name', 'Strength - 1RM 100% Template')->firstOrFail();

    expect($backSquat->owner_id)->toBe($coach->id)
        ->and($backSquat->template_id)->toBe(4)
        ->and($backSquat->config->reps?->mode)->toBe('automatic')
        ->and($backSquat->config->weight?->mode)->toBe('automatic')
        ->and($backSquat->config->weight?->oneRepMaxModifier)->toBe(100);

    $light1rm = Exercise::query()->where('name', 'Strength - 1RM 90%')->firstOrFail();

    expect($light1rm->name)->toBe('Strength - 1RM 90%')
        ->and($light1rm->config->weight?->mode)->toBe('automatic')
        ->and($light1rm->config->weight?->oneRepMaxModifier)->toBe(90);

    $heavy1rm = Exercise::query()->where('name', 'Strength - 1RM 110%')->firstOrFail();

    expect($heavy1rm->name)->toBe('Strength - 1RM 110%')
        ->and($heavy1rm->config->weight?->mode)->toBe('automatic')
        ->and($heavy1rm->config->weight?->oneRepMaxModifier)->toBe(110);

    $coachDefinedWeight = Exercise::query()->where('name', 'Strength - Coach Fixed Weight')->firstOrFail();

    expect($coachDefinedWeight->name)->toBe('Strength - Coach Fixed Weight')
        ->and($coachDefinedWeight->config->weight?->mode)->toBe('manual')
        ->and($coachDefinedWeight->config->weight?->default)->toBe(5.0);

    $athleteEnteredWeight = Exercise::query()->where('name', 'Strength - Athlete Enters Weight')->firstOrFail();

    expect($athleteEnteredWeight->name)->toBe('Strength - Athlete Enters Weight')
        ->and($athleteEnteredWeight->config->reps?->default)->toBe('8-10')
        ->and($athleteEnteredWeight->config->weight?->mode)->toBe('manual')
        ->and($athleteEnteredWeight->config->weight?->default)->toBeNull();

    $joggingWarmUp = Exercise::query()->where('name', 'Endurance - Auto HR Jogging')->firstOrFail();

    expect($joggingWarmUp->name)->toBe('Endurance - Auto HR Jogging')
        ->and($joggingWarmUp->config->heartRate?->mode)->toBe('automatic_jogging');

    $intervalRun = Exercise::query()->where('name', 'Endurance - Manual HR Intervals Zone')->firstOrFail();

    expect($intervalRun->name)->toBe('Endurance - Manual HR Intervals Zone')
        ->and($intervalRun->config->sets->label)->toBe('Interval')
        ->and($intervalRun->config->heartRate?->mode)->toBe('manual')
        ->and($intervalRun->config->heartRate?->default)->toBe('150-160')
        ->and($intervalRun->config->heartRateZone?->default)->toBe('3');

    $shuttleSprint = Exercise::query()->where('name', 'Endurance - Manual Rounds Duration')->firstOrFail();

    expect($shuttleSprint->name)->toBe('Endurance - Manual Rounds Duration')
        ->and($shuttleSprint->config->settings)->toBe(['reps', 'duration'])
        ->and($shuttleSprint->config->duration?->unit)->toBe('seconds');

    $manualHeartRate = Exercise::query()->where('name', 'Recovery - Manual HR Watts')->firstOrFail();

    expect($manualHeartRate->name)->toBe('Recovery - Manual HR Watts')
        ->and($manualHeartRate->config->heartRate?->mode)->toBe('manual')
        ->and($manualHeartRate->config->heartRate?->default)->toBe('140');

    $warmUp = Exercise::query()->where('name', 'Strength - Warm-Up Prep')->firstOrFail();
    $warmDown = Exercise::query()->where('name', 'Recovery - Warm-Down Reset')->firstOrFail();

    expect($warmUp->owner_id)->toBe($coach->id)
        ->and($warmUp->config->duration?->default)->toBe('8')
        ->and($warmDown->owner_id)->toBe($coach->id)
        ->and($warmDown->config->duration?->default)->toBe('6');

    $strengthProgram = ExerciseProgram::query()
        ->where('owner_id', $coach->id)
        ->where('name', 'Strength - Test Program')
        ->firstOrFail();

    $scheduledStrengthProgram = TrainingProgram::query()
        ->where('owner_id', $coach->id)
        ->whereHas('program', fn ($query) => $query->where('name', 'Strength - Test Program'))
        ->firstOrFail();

    $strengthStructure = $strengthProgram->exercises()
        ->withPivot(['type', 'group'])
        ->orderBy('exercise_program_exercises.sort')
        ->get()
        ->map(fn (Exercise $exercise) => [
            'name' => $exercise->name,
            'type' => $exercise->pivot->type,
            'group' => $exercise->pivot->group,
        ])
        ->all();

    expect($strengthStructure)->toBe([
        ['name' => 'Strength - Warm-Up Prep', 'type' => 'warm_up', 'group' => null],
        ['name' => 'Strength - Coach Fixed Weight', 'type' => 'main', 'group' => 'A'],
        ['name' => 'Strength - 1RM 90%', 'type' => 'main', 'group' => 'A'],
        ['name' => 'Strength - 1RM 110%', 'type' => 'main', 'group' => 'B'],
        ['name' => 'Strength - Athlete Enters Weight', 'type' => 'main', 'group' => 'B'],
        ['name' => 'Strength - 1RM 100% Template', 'type' => 'main', 'group' => null],
        ['name' => 'Recovery - Warm-Down Reset', 'type' => 'warm_down', 'group' => null],
    ]);

    expect($strengthProgram->parent_type)->toBe(TrainingProgram::class)
        ->and($strengthProgram->parent_id)->toBe($scheduledStrengthProgram->id);

    $archivedProgram = TrainingProgram::query()
        ->where('owner_id', $coach->id)
        ->where('status', TrainingProgram::STATUS_ARCHIVED)
        ->firstOrFail();

    expect($archivedProgram->statusValue())->toBe(TrainingProgram::STATUS_ARCHIVED)
        ->and($archivedProgram->program?->name)->toBe('Example Archived Strength');

    $archivedBlock = TrainingProgramBlock::query()
        ->where('note', 'Archived Strength Block')
        ->firstOrFail();

    $strengthBlock = TrainingProgramBlock::query()
        ->where('note', 'Strength Block')
        ->firstOrFail();

    expect($archivedBlock->start?->format('Y-m-d'))->toBe(today()->subWeeks(7)->subDay()->format('Y-m-d'))
        ->and($archivedBlock->end?->format('Y-m-d'))->toBe(today()->subWeeks(4)->subDay()->format('Y-m-d'))
        ->and($archivedBlock->end?->lt($strengthBlock->start))->toBeTrue();

    expect(TrainingProgramSlot::query()
        ->where('training_program_id', $archivedProgram->id)
        ->count())->toBe(18);

    $overrideProgram = TrainingProgram::query()
        ->where('owner_id', $coach->id)
        ->whereHas('program', fn ($query) => $query->where('name', 'Example Override Lab'))
        ->firstOrFail();

    expect($overrideProgram->statusValue())->toBe(TrainingProgram::STATUS_ACTIVE)
        ->and(TrainingProgramSlot::query()->where('training_program_id', $overrideProgram->id)->count())->toBe(48);

    $john = User::query()->where('forename', 'John')->where('surname', 'Doe')->firstOrFail();
    $max = User::query()->where('forename', 'Max')->where('surname', 'Mustermann')->firstOrFail();
    $joe = User::query()->where('forename', 'Joe')->where('surname', 'Bloggs')->firstOrFail();

    $johnOneRepMaxMetrics = MetricSubmission::query()
        ->where('user_id', $john->id)
        ->where('metric', 'oneRepMax')
        ->orderBy('recorded_at')
        ->get();
    $joeOneRepMaxMetrics = MetricSubmission::query()
        ->where('user_id', $joe->id)
        ->where('metric', 'oneRepMax')
        ->orderBy('recorded_at')
        ->get();

    expect($johnOneRepMaxMetrics)->toHaveCount(2)
        ->and($joeOneRepMaxMetrics)->toHaveCount(1)
        ->and($johnOneRepMaxMetrics->first()?->recorded_at?->lte($strengthBlock->start))->toBeTrue()
        ->and($joeOneRepMaxMetrics->first()?->recorded_at?->lte($strengthBlock->start))->toBeTrue()
        ->and($johnOneRepMaxMetrics->last()?->recorded_at?->betweenIncluded($strengthBlock->start, $strengthBlock->end))->toBeTrue();

    $johnOverrideSlots = TrainingProgramSlot::query()
        ->where('training_program_id', $overrideProgram->id)
        ->where('user_id', $john->id)
        ->orderBy('datetime')
        ->get()
        ->map(fn (TrainingProgramSlot $slot) => $slot->fresh('exercises.exercise', 'exercises.sets.values'))
        ->values();
    $maxOverrideSlot = TrainingProgramSlot::query()
        ->where('training_program_id', $overrideProgram->id)
        ->where('user_id', $max->id)
        ->orderBy('datetime')
        ->firstOrFail()
        ->fresh('exercises.exercise', 'exercises.sets.values');
    $joeOverrideSlot = TrainingProgramSlot::query()
        ->where('training_program_id', $overrideProgram->id)
        ->where('user_id', $joe->id)
        ->orderBy('datetime')
        ->firstOrFail()
        ->fresh('exercises.exercise', 'exercises.sets.values');

    $johnOverrideSlot = $johnOverrideSlots->firstOrFail();
    $johnWeekOneSecondSlot = $johnOverrideSlots[1];
    $johnWeekTwoFirstSlot = $johnOverrideSlots[2];
    $johnWeekTwoSecondSlot = $johnOverrideSlots[3];

    $johnExercises = $johnOverrideSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);
    $johnWeekOneSecondExercises = $johnWeekOneSecondSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);
    $johnWeekTwoFirstExercises = $johnWeekTwoFirstSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);
    $johnWeekTwoSecondExercises = $johnWeekTwoSecondSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);
    $maxExercises = $maxOverrideSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);
    $joeExercises = $joeOverrideSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);

    expect($johnExercises['Override - Reps Chain']->sets->first()->values->firstWhere('setting_key', 'reps')?->planned_string_value)->toBe('8')
        ->and($joeExercises['Override - Reps Chain']->sets->first()->values->firstWhere('setting_key', 'reps')?->planned_string_value)->toBe('10');

    expect($johnExercises['Override - Cell Reps Chain']->sets->first()->values->firstWhere('setting_key', 'reps')?->planned_string_value)->toBe('6')
        ->and($joeExercises['Override - Cell Reps Chain']->sets->first()->values->firstWhere('setting_key', 'reps')?->planned_string_value)->toBe('8');

    $johnHrSets = $johnExercises['Override - Auto HR Jogging Zones']->sets->sortBy('set_number')->values();

    expect($johnHrSets->map(fn ($set) => $set->values->firstWhere('setting_key', 'heartRate')?->planned_string_value)->all())
        ->toBe([
            '105-132',
            '133-151',
            '162-180',
            '181-189',
            '190-200',
        ]);

    expect($johnExercises['Override - Auto HR Biking Combo']->sets->first()->values->firstWhere('setting_key', 'watts')?->planned_value_type)->toBe('int')
        ->and($johnExercises['Override - Auto HR Biking Combo']->sets->first()->values->firstWhere('setting_key', 'watts')?->planned_int_value)->toBe(120)
        ->and($joeExercises->has('Override - Auto HR Biking Combo'))->toBeFalse();

    expect($johnExercises->has('Override - Disabled Chain'))->toBeFalse()
        ->and($joeExercises->has('Override - Disabled Chain'))->toBeTrue();

    expect($johnExercises['Override - Settings Visibility']->sets->first()->values->pluck('setting_key')->all())
        ->toBe(['reps', 'rest'])
        ->and($joeExercises['Override - Settings Visibility']->sets->first()->values->pluck('setting_key')->sort()->values()->all())
        ->toBe(['note', 'reps', 'rest', 'tempo'])
        ->and($joeExercises['Override - Settings Visibility']->sets->first()->values->firstWhere('setting_key', 'tempo')?->planned_string_value)
        ->toBe('2111')
        ->and($joeExercises['Override - Settings Visibility']->sets->first()->values->firstWhere('setting_key', 'note')?->planned_string_value)
        ->toBe('Stay tall');

    expect($johnExercises['Endurance - Pace Override']->sets->first()->values->firstWhere('setting_key', 'pace')?->planned_string_value)->toBe('4:30')
        ->and($joeExercises['Endurance - Pace Override']->sets->first()->values->firstWhere('setting_key', 'pace')?->planned_string_value)->toBe('4:15');

    expect($johnExercises['Override - Week Rest']->sets->first()->values->firstWhere('setting_key', 'rest')?->planned_int_value)->toBe(75)
        ->and($johnWeekOneSecondExercises['Override - Week Rest']->sets->first()->values->firstWhere('setting_key', 'rest')?->planned_int_value)->toBe(75)
        ->and($johnWeekTwoFirstExercises['Override - Week Rest']->sets->first()->values->firstWhere('setting_key', 'rest')?->planned_int_value)->toBe(120)
        ->and($johnWeekTwoSecondExercises['Override - Week Rest']->sets->first()->values->firstWhere('setting_key', 'rest')?->planned_int_value)->toBe(120);

    expect($joeExercises->has('Override - Auto HR Jogging Zones'))->toBeFalse();

    expect($maxExercises->has('Strength - 1RM 100% Template'))->toBeFalse()
        ->and($johnExercises->has('Override - Missing 1RM Disabled'))->toBeTrue()
        ->and($maxExercises->has('Override - Missing 1RM Disabled'))->toBeFalse()
        ->and($johnExercises->has('Override - Missing HR Disabled'))->toBeTrue()
        ->and($joeExercises->has('Override - Missing HR Disabled'))->toBeFalse();

    $overrideProgramModel = $overrideProgram->program()->with('exercises')->firstOrFail();
    $startsAtExercise = $overrideProgramModel->exercises->firstWhere('name', 'Override - Starts At Date');
    $startsAtPivotId = (int) $startsAtExercise?->pivot?->id;

    expect($startsAtPivotId)->toBeGreaterThan(0)
        ->and($overrideProgramModel->config->defaultExerciseOverrides($startsAtPivotId)->startsAtDate)->toBe(today()->addWeeks(2)->format('Y-m-d'))
        ->and($overrideProgramModel->config->defaultExerciseOverrides($startsAtPivotId)->sessionGrouping?->toArray())->toBe([
            'mode' => 'groups',
            'groupSize' => 3,
            'copyValuesAutomatically' => false,
        ])
        ->and($overrideProgramModel->config->userExerciseOverrides($joe->id, $startsAtPivotId)->startsAtDate)->toBe(today()->addWeek()->format('Y-m-d'))
        ->and($overrideProgramModel->config->userExerciseOverrides($joe->id, $startsAtPivotId)->sessionGrouping?->toArray())->toBe([
            'mode' => 'groups',
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ]);

    $fourWeeksAgo = today()->subWeeks(4)->format('Y-m-d');
    $tomorrow = today()->addDay()->format('Y-m-d');
    $activeFirstScheduledDate = Carbon::parse((string) TrainingProgramSlot::query()
        ->where('training_program_id', '!=', $archivedProgram->id)
        ->min('scheduled_date'));
    $lastScheduledDate = Carbon::parse((string) TrainingProgramSlot::query()->max('scheduled_date'));

    expect($activeFirstScheduledDate->format('Y-m-d'))->toBe($fourWeeksAgo);

    TrainingProgramBlock::query()
        ->orderBy('id')
        ->whereNotIn('id', [$archivedBlock->id])
        ->each(function (TrainingProgramBlock $block) use ($tomorrow, $fourWeeksAgo, $lastScheduledDate): void {
            if ($block->note === 'Strength Block') {
                expect($block->start?->format('Y-m-d'))->toBe($fourWeeksAgo)
                    ->and($block->end?->gte($block->start?->copy()->addWeeks(8)))->toBeTrue();

                return;
            }

            expect($block->start?->format('Y-m-d'))->toBe($tomorrow)
                ->and($block->end?->gte($lastScheduledDate))->toBeTrue();
        });

    expect(TrainingProgramSlot::query()
        ->where('user_id', $max->id)
        ->whereDate('scheduled_date', today()->subWeeks(3)->addDays(4)->format('Y-m-d'))
        ->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()
            ->where('user_id', $joe->id)
            ->whereDate('scheduled_date', today()->subWeeks(2)->addDays(1)->format('Y-m-d'))
            ->exists())->toBeTrue();

    $pastStrengthSlots = TrainingProgramSlot::query()
        ->with('exercises')
        ->where('training_program_id', $strengthTrainingProgram->id)
        ->whereDate('scheduled_date', '<', today()->format('Y-m-d'))
        ->get();

    expect($pastStrengthSlots)->toHaveCount(24)
        ->and($pastStrengthSlots->filter(fn (TrainingProgramSlot $slot) => $slot->status?->value === 'completed')->count())->toBeGreaterThan(0)
        ->and($pastStrengthSlots->filter(fn (TrainingProgramSlot $slot) => $slot->status?->value === 'partially_completed')->count())->toBeGreaterThan(0)
        ->and($pastStrengthSlots->filter(fn (TrainingProgramSlot $slot) => $slot->status?->value === 'skipped')->count())->toBeGreaterThan(0);

    $johnFirstPastStrengthSlot = TrainingProgramSlot::query()
        ->with('exercises.exercise', 'exercises.sets.values')
        ->where('training_program_id', $strengthTrainingProgram->id)
        ->where('user_id', $john->id)
        ->whereDate('scheduled_date', '<', today()->format('Y-m-d'))
        ->orderBy('scheduled_date')
        ->firstOrFail();

    $johnFirstPastStrengthExercises = $johnFirstPastStrengthSlot->exercises->keyBy(fn ($exercise) => $exercise->exercise->name);

    expect($johnFirstPastStrengthSlot->status?->value)->toBe('completed')
        ->and($johnFirstPastStrengthExercises['Strength - Coach Fixed Weight']->sets->sortBy('set_number')->values()->first()->values->firstWhere('setting_key', 'weight')?->actual_decimal_value)->toBe(7.5)
        ->and($johnFirstPastStrengthExercises['Strength - Athlete Enters Weight']->sets->sortBy('set_number')->values()->map(
            function ($set) {
                $weight = $set->values->firstWhere('setting_key', 'weight');

                return $weight?->actual_decimal_value ?? $weight?->actual_int_value ?? $weight?->actual_string_value;
            }
        )->all())->toBe([22.5, 25, 27.5])
        ->and($johnFirstPastStrengthExercises['Strength - Coach Fixed Weight']->sets->flatMap(
            fn ($set) => $set->values->pluck('actual_value_type')
        )->contains(null))->toBeFalse()
        ->and($johnFirstPastStrengthExercises['Strength - Athlete Enters Weight']->sets->flatMap(
            fn ($set) => $set->values->pluck('actual_value_type')
        )->contains(null))->toBeFalse();

    File::delete($envPath);
});

it('removes Conall owned sandbox data before recreating the fixture bundle', function () {
    $envPath = base_path('tests/Fixtures/tmp.exercise-fixture-reset.env');
    File::ensureDirectoryExists(dirname($envPath));
    File::put($envPath, "APP_ENV=testing\n");
    config()->set('app.test_user_env_file', $envPath);

    $coach = User::create([
        'type' => 'coach',
        'forename' => 'Conall',
        'surname' => "O'Reilly",
        'email' => 'conall@coda.works',
        'password' => Hash::make('old-password'),
        'config' => [],
    ]);

    $otherCoach = User::create([
        'type' => 'coach',
        'forename' => 'Other',
        'surname' => 'Coach',
        'email' => 'other@example.com',
        'password' => Hash::make('password'),
        'config' => [],
    ]);

    $oldAthlete = User::create([
        'owner_id' => $coach->id,
        'type' => 'athlete',
        'forename' => 'Old',
        'surname' => 'Athlete',
        'password' => Hash::make('password'),
        'config' => [],
    ]);

    $tag = Tag::create([
        'scope' => 'exercise_category',
        'name' => 'Legacy',
        'slug' => 'legacy',
        'sort_order' => 0,
    ]);

    $oldExercise = Exercise::create([
        'owner_id' => $coach->id,
        'name' => 'Legacy Exercise',
        'category_id' => $tag->id,
        'config' => ['settings' => []],
    ]);

    $oldProgram = ExerciseProgram::create([
        'owner_id' => $coach->id,
        'name' => 'Legacy Program',
        'exercise_category_id' => $tag->id,
    ]);

    $group = UserGroup::create([
        'owner_id' => $coach->id,
        'name' => 'Legacy Group',
        'config' => [],
    ]);

    $trainingProgram = TrainingProgram::create([
        'owner_id' => $coach->id,
        'group_id' => $group->id,
        'exercise_program_id' => $oldProgram->id,
        'sort' => 0,
    ]);

    $block = TrainingProgramBlock::create([
        'owner_id' => $coach->id,
        'group_id' => $group->id,
        'user_id' => $oldAthlete->id,
        'type' => 'note',
        'start' => now()->toDateString(),
        'end' => now()->toDateString(),
        'note' => 'legacy',
        'active' => true,
    ]);

    $slot = TrainingProgramSlot::create([
        'owner_id' => $coach->id,
        'training_program_id' => $trainingProgram->id,
        'user_id' => $oldAthlete->id,
        'datetime' => now(),
        'scheduled_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $submission = MetricSubmission::create([
        'user_id' => $oldAthlete->id,
        'metric' => 'heartRate',
        'recorded_by' => $coach->id,
        'recorded_at' => now()->toDateString(),
        'owner_type' => TrainingProgramBlock::class,
        'owner_id' => $block->id,
    ]);

    $this->artisan('db:import-exercise-fixture')->assertExitCode(0);

    expect(User::query()->find($oldAthlete->id))->toBeNull()
        ->and(Exercise::withTrashed()->find($oldExercise->id))->toBeNull()
        ->and(ExerciseProgram::withTrashed()->find($oldProgram->id))->toBeNull()
        ->and(UserGroup::withTrashed()->find($group->id))->toBeNull()
        ->and(TrainingProgram::query()->find($trainingProgram->id))->toBeNull()
        ->and(TrainingProgramBlock::withTrashed()->find($block->id))->toBeNull()
        ->and(TrainingProgramSlot::query()->find($slot->id))->toBeNull()
        ->and(MetricSubmission::withTrashed()->find($submission->id))->toBeNull()
        ->and(User::query()->find($otherCoach->id))->not->toBeNull();

    expect(User::query()->where('owner_id', $coach->id)->count())->toBe(3)
        ->and(Exercise::query()->where('owner_id', $coach->id)->count())->toBe(24)
        ->and(Tag::query()->where('id', 302)->exists())->toBeFalse();

    File::delete($envPath);
});
