<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;

function createTaggedTrainingProgram(UserGroup $group, string $name, ?Tag $category = null): TrainingProgram
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

it('returns grouped slot details with aggregate statuses when no user is selected', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $program = createTaggedTrainingProgram($group, 'Strength AM');
    $athleteOne = User::factory()->athlete()->create(['forename' => 'Alice', 'surname' => 'Able']);
    $athleteTwo = User::factory()->athlete()->create(['forename' => 'Bob', 'surname' => 'Baker']);
    $athleteThree = User::factory()->athlete()->create(['forename' => 'Cara', 'surname' => 'Cole']);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteTwo->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteThree->id,
        'datetime' => Carbon::parse('2030-04-10 14:30:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.slot-details', [
        'training_program_id' => $program->id,
        'date' => '2030-04-10',
    ]));

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.time', '09:00')
        ->assertJsonPath('0.names.0', 'Alice Able')
        ->assertJsonPath('0.names.1', 'Bob Baker')
        ->assertJsonPath('0.statusColor.light', '110 231 183')
        ->assertJsonPath('0.statusColor.dark', '52 211 153')
        ->assertJsonPath('1.time', '14:30')
        ->assertJsonPath('1.names.0', 'Cara Cole')
        ->assertJsonPath('1.statusColor.light', '228 228 231')
        ->assertJsonPath('1.statusColor.dark', '161 161 170');
});

it('returns per-user slot details when a user is selected', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $program = createTaggedTrainingProgram($group, 'Tempo');
    $selectedAthlete = User::factory()->athlete()->create(['forename' => 'Nina', 'surname' => 'North']);
    $otherAthlete = User::factory()->athlete()->create(['forename' => 'Otto', 'surname' => 'Oak']);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $selectedAthlete->id,
        'datetime' => Carbon::parse('2030-04-10 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $otherAthlete->id,
        'datetime' => Carbon::parse('2030-04-10 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.slot-details', [
        'training_program_id' => $program->id,
        'date' => '2030-04-10',
        'user_id' => $selectedAthlete->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.time', '07:15')
        ->assertJsonPath('0.name', 'Nina North')
        ->assertJsonPath('0.userId', $selectedAthlete->id)
        ->assertJsonPath('0.statusColor.light', '252 211 77')
        ->assertJsonPath('0.statusColor.dark', '251 191 36');
});

it('returns member color counts grouped by user and date', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $redCategory = Tag::factory()->create(['color' => 'red', 'scope' => 'training_category']);
    $blueCategory = Tag::factory()->create(['color' => 'blue', 'scope' => 'training_category']);
    $strengthProgram = createTaggedTrainingProgram($group, 'Strength', $redCategory);
    $conditioningProgram = createTaggedTrainingProgram($group, 'Conditioning', $blueCategory);
    $athlete = User::factory()->athlete()->create();

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $strengthProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $strengthProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 10:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $conditioningProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-11 08:00:00'),
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.slot-member-colors', [
        'group_id' => $group->id,
        'start' => '2030-04-10',
        'end' => '2030-04-11',
    ]));

    $response->assertOk()
        ->assertJsonPath("{$athlete->id}.2030-04-10.red", 2)
        ->assertJsonPath("{$athlete->id}.2030-04-11.blue", 1);
});

it('returns grouped week-page entries split into am and pm sessions', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['color' => 'green', 'scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Strength', $category);
    $athleteOne = User::factory()->athlete()->create(['forename' => 'Amy', 'surname' => 'Ash']);
    $athleteTwo = User::factory()->athlete()->create(['forename' => 'Ben', 'surname' => 'Birch']);
    $athleteThree = User::factory()->athlete()->create(['forename' => 'Zoe', 'surname' => 'Zinc']);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteTwo->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteThree->id,
        'datetime' => Carbon::parse('2030-04-10 13:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.slot-week-page', [
        'group_id' => $group->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath('2030-04-10.am.0.name', 'Strength')
        ->assertJsonPath('2030-04-10.am.0.color', 'green')
        ->assertJsonPath('2030-04-10.am.0.userNames.0', 'Amy Ash')
        ->assertJsonPath('2030-04-10.am.0.userNames.1', 'Ben Birch')
        ->assertJsonPath('2030-04-10.am.0.statusColor.light', '252 211 77')
        ->assertJsonPath('2030-04-10.pm.0.time', '13:00')
        ->assertJsonPath('2030-04-10.pm.0.userNames.0', 'Zoe Zinc')
        ->assertJsonPath('2030-04-10.pm.0.statusColor.light', '125 211 252');
});

it('returns user week-page entries without group member names when a user is selected', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['color' => 'orange', 'scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Intervals', $category);
    $selectedAthlete = User::factory()->athlete()->create();
    $otherAthlete = User::factory()->athlete()->create();

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $selectedAthlete->id,
        'datetime' => Carbon::parse('2030-04-10 14:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $otherAthlete->id,
        'datetime' => Carbon::parse('2030-04-10 15:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.slot-week-page', [
        'group_id' => $group->id,
        'user_id' => $selectedAthlete->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath('2030-04-10.pm.0.name', 'Intervals')
        ->assertJsonPath('2030-04-10.pm.0.time', '14:15')
        ->assertJsonPath('2030-04-10.pm.0.color', 'orange')
        ->assertJsonPath('2030-04-10.pm.0.userNames', [])
        ->assertJsonPath('2030-04-10.pm.0.statusColor.light', '110 231 183');
});

it('assigns session numbers and aggregate statuses for grouped grid cells', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Strength', $category);
    $athleteOne = User::factory()->athlete()->create();
    $athleteTwo = User::factory()->athlete()->create();
    $athleteThree = User::factory()->athlete()->create();

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2030-04-01',
        'end' => '2030-04-30',
        'note' => 'April Block',
        'active' => true,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteTwo->id,
        'datetime' => Carbon::parse('2030-04-10 09:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteThree->id,
        'datetime' => Carbon::parse('2030-04-10 09:30:00'),
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.program-grid-cells', [
        'group_id' => $group->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath("{$program->id}-2030-04-10.session", 1)
        ->assertJsonPath("{$program->id}-2030-04-10.completedCount", 1)
        ->assertJsonPath("{$program->id}-2030-04-10.skippedCount", 1)
        ->assertJsonPath("{$program->id}-2030-04-10.partialCount", 1)
        ->assertJsonPath("{$program->id}-2030-04-10.status", 'partially_completed')
        ->assertJsonPath("{$program->id}-2030-04-12.session", 2)
        ->assertJsonPath("{$program->id}-2030-04-12.status", 'pending');
});

it('uses user overrides when computing grid session numbers', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Strength', $category);
    $athlete = User::factory()->athlete()->create();

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2030-04-01',
        'end' => '2030-04-30',
        'note' => 'April Block',
        'active' => true,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'parent_id' => $parentBlock->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2030-04-11',
        'end' => '2030-04-30',
        'note' => 'Delayed Start',
        'active' => true,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.program-grid-cells', [
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonMissingPath("{$program->id}-2030-04-10.session")
        ->assertJsonPath("{$program->id}-2030-04-10.time", '07:15')
        ->assertJsonPath("{$program->id}-2030-04-12.session", 1)
        ->assertJsonPath("{$program->id}-2030-04-12.status", 'completed');
});

it('counts multiple same-day sessions when deriving later grid session numbers', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Strength', $category);
    $athlete = User::factory()->athlete()->create();

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2030-04-01',
        'end' => '2030-04-30',
        'note' => 'April Block',
        'active' => true,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 16:45:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.program-grid-cells', [
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath("{$program->id}-2030-04-10.session", 1)
        ->assertJsonPath("{$program->id}-2030-04-12.session", 3);
});

it('returns formatted user day slots for a single day', function () {
    $admin = User::factory()->admin()->create();
    $group = UserGroup::create(['name' => 'Squad']);
    $category = Tag::factory()->create(['color' => 'teal', 'scope' => 'training_category']);
    $program = createTaggedTrainingProgram($group, 'Bike', $category);
    $athlete = User::factory()->athlete()->create();

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 06:45:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $program->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-11 06:45:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.user-day-slots', [
        'user_id' => $athlete->id,
        'date' => '2030-04-10',
    ]));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.time', '06:45')
        ->assertJsonPath('0.name', 'Bike')
        ->assertJsonPath('0.color', 'teal')
        ->assertJsonPath('0.statusColor.light', '125 211 252')
        ->assertJsonPath('0.statusColor.dark', '56 189 248');
});
