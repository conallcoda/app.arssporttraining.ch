<?php

use App\Livewire\Athlete\ProgramDetails;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;

it('links scheduled programs to the athlete program details page', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Front Squat'])->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/day/2026-04-03')
        ->assertOk()
        ->assertSee('/programs/2026-04-03/'.$trainingProgram->id, false);
});

it('shows all exercises in the selected program', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseOne = Exercise::factory()->create([
        'name' => 'Front Squat',
        'instructions' => 'Stay tall through the lift.',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => [
                'default' => 4,
                'label' => 'Set',
                'deload' => 'none',
            ],
            'reps' => [
                'mode' => 'manual',
                'default' => 8,
                'applyPer' => 'session',
            ],
            'weight' => [
                'mode' => 'manual',
                'default' => 5,
                'applyPer' => 'session',
            ],
            'tempo' => [
                'default' => '2020',
                'applyPer' => 'week',
            ],
            'rest' => [
                'default' => 45,
                'applyPer' => 'week',
            ],
            'preview' => [
                'weeks' => 1,
                'sessionsPerWeek' => 1,
            ],
        ],
    ]);
    $exerciseTwo = Exercise::factory()->create([
        'name' => 'Romanian Deadlift',
        'instructions' => 'Keep tension in the hamstrings.',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseOne->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseTwo->id,
        'sort' => 1,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/'.$trainingProgram->id.'?from=%2Fdashboard%2Fcalendar%2Fweek%2F2026-03-30')
        ->assertOk()
        ->assertSeeLivewire(ProgramDetails::class)
        ->assertSee('Friday Strength')
        ->assertSee('Front Squat')
        ->assertSee('Romanian Deadlift')
        ->assertSee('Reps')
        ->assertSee('Weight (kg)')
        ->assertSee('Tempo')
        ->assertSee('2020')
        ->assertSee('Rest')
        ->assertSee('45')
        ->assertSee('Stay tall through the lift.')
        ->assertSee('/dashboard/calendar/week/2026-03-30', false);
});

it('returns 404 when the selected program is not scheduled for the athlete on that date', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/999999')
        ->assertNotFound();
});
