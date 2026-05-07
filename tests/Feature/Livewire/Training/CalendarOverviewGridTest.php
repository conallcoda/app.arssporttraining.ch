<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarOverviewGrid;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Livewire\Livewire;

function mountOverviewGrid(User $coach, array $props = []): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($coach)->test(CalendarOverviewGrid::class, array_merge([
        'groupFilter' => 'mine',
        'calendarSettings' => new CalendarSettingsData(
            start: '2030-04-08',
            end: '2030-04-14',
            preset: CalendarDateService::PRESET_CUSTOM,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ], $props));
}

function createOverviewProgram(UserGroup $group, string $name, ?Tag $category = null): TrainingProgram
{
    $exerciseProgram = ExerciseProgram::factory()->create([
        'name' => $name,
        'exercise_category_id' => $category?->id,
    ]);

    return TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);
}

it('builds overview data for owned groups only when filtered to mine', function () {
    $coach = User::factory()->coach()->create();
    $ownedAthlete = User::factory()->athlete()->create();
    $sharedAthlete = User::factory()->athlete()->create();
    $ownedGroup = UserGroup::create(['name' => 'Owned', 'owner_id' => $coach->id]);
    $sharedGroup = UserGroup::create(['name' => 'Shared']);
    $ownedGroup->members()->attach($ownedAthlete);
    $sharedGroup->members()->attach($sharedAthlete);

    $redCategory = Tag::factory()->create(['color' => 'red', 'scope' => 'training_category']);
    $blueCategory = Tag::factory()->create(['color' => 'blue', 'scope' => 'training_category']);
    $ownedProgram = createOverviewProgram($ownedGroup, 'Strength', $redCategory);
    $sharedProgram = createOverviewProgram($sharedGroup, 'Conditioning', $blueCategory);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $ownedProgram->id,
        'user_id' => $ownedAthlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $sharedProgram->id,
        'user_id' => $sharedAthlete->id,
        'datetime' => Carbon::parse('2030-04-11 09:00:00'),
    ]);

    $component = mountOverviewGrid($coach);
    $overviewData = $component->instance()->overviewData;

    expect($overviewData)->toHaveCount(1)
        ->and($overviewData[0]['group']->id)->toBe($ownedGroup->id)
        ->and($overviewData[0]['dates'])->toHaveKey('2030-04-10')
        ->and($overviewData[0]['dateColors']['2030-04-10']['red'])->toBe(1)
        ->and($overviewData[0]['members'][0]['user']->id)->toBe($ownedAthlete->id);
});

it('excludes coaches from rendered group members even if they are attached', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Owned', 'owner_id' => $coach->id]);

    $group->members()->attach([$coach->id, $athlete->id]);

    $component = mountOverviewGrid($coach);
    $overviewData = $component->instance()->overviewData;

    expect($overviewData)->toHaveCount(1)
        ->and(collect($overviewData[0]['members'])->pluck('user.id')->all())->toBe([$athlete->id]);
});

it('refreshes overview data when the group filter changes', function () {
    $coach = User::factory()->coach()->create();
    $ownedGroup = UserGroup::create(['name' => 'Owned', 'owner_id' => $coach->id]);
    $sharedGroup = UserGroup::create(['name' => 'Shared']);

    $component = mountOverviewGrid($coach);
    expect(collect($component->instance()->overviewData)->pluck('group.id')->all())
        ->toBe([$ownedGroup->id]);

    $component->dispatch('group-filter-changed', filter: 'all')
        ->assertSet('groupFilter', 'all');

    expect(collect($component->instance()->overviewData)->pluck('group.id')->all())
        ->toBe([$ownedGroup->id, $sharedGroup->id]);
});

it('updates calendar settings and date-derived collections when the range changes', function () {
    $coach = User::factory()->coach()->create();

    $component = mountOverviewGrid($coach);
    expect($component->instance()->days)->toHaveCount(7);

    $component->dispatch('calendar-range-changed', settings: [
        'start' => '2030-05-01',
        'end' => '2030-05-03',
        'preset' => CalendarDateService::PRESET_CUSTOM,
    ], weekStartsOn: Carbon::MONDAY, weekEndsOn: Carbon::SUNDAY)
        ->assertSet('calendarSettings.start', '2030-05-01')
        ->assertSet('calendarSettings.end', '2030-05-03');

    expect($component->instance()->days[0]['date'])->toBe('2030-04-29')
        ->and(last($component->instance()->days)['date'])->toBe('2030-05-05')
        ->and($component->instance()->weeks)->toHaveCount(1)
        ->and($component->instance()->months[0]['label'])->toBe('April 2030')
        ->and($component->instance()->months[1]['label'])->toBe('May 2030');
});

it('dispatches overview selections for groups and users', function () {
    $coach = User::factory()->coach()->create();

    $component = mountOverviewGrid($coach);

    $component->call('selectFromOverview', 10)
        ->assertDispatched('overview-selection', selected: [['group' => 10]]);

    $component->call('selectFromOverview', 10, 99)
        ->assertDispatched('overview-selection', selected: [['group' => 10, 'user' => 99]]);
});
