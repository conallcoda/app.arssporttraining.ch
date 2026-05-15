<?php

use App\Console\Commands\FixDataMay2026Command;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('repairs shared, mismatched, library-backed, and orphaned scheduled exercise programs', function () {
    $group = UserGroup::create(['name' => 'Team Alpha']);

    $sharedProgram = ExerciseProgram::factory()->create([
        'parent_type' => TrainingProgram::class,
        'parent_id' => null,
    ]);

    $ownerTrainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $sharedProgram->id,
    ]);

    $sharedProgram->update(['parent_id' => $ownerTrainingProgram->id]);

    $secondSharedTrainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $sharedProgram->id,
    ]);

    $mismatchedProgram = ExerciseProgram::factory()->create([
        'parent_type' => TrainingProgram::class,
        'parent_id' => 999999,
    ]);

    $mismatchedTrainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $mismatchedProgram->id,
    ]);

    $libraryProgram = ExerciseProgram::factory()->create([
        'parent_type' => null,
        'parent_id' => null,
    ]);

    $libraryBackedTrainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $libraryProgram->id,
    ]);

    $orphanedScheduledProgram = ExerciseProgram::factory()->create([
        'parent_type' => TrainingProgram::class,
        'parent_id' => 123456,
    ]);

    $this->artisan(FixDataMay2026Command::SIGNATURE)
        ->assertExitCode(0);

    $ownerTrainingProgram->refresh();
    $secondSharedTrainingProgram->refresh();
    $mismatchedTrainingProgram->refresh();
    $libraryBackedTrainingProgram->refresh();

    expect($ownerTrainingProgram->exercise_program_id)->toBe($sharedProgram->id)
        ->and($secondSharedTrainingProgram->exercise_program_id)->not->toBe($sharedProgram->id)
        ->and($secondSharedTrainingProgram->program->parent_id)->toBe($secondSharedTrainingProgram->id)
        ->and($sharedProgram->fresh()->parent_id)->toBe($ownerTrainingProgram->id);

    expect($mismatchedProgram->fresh()->parent_id)->toBe($mismatchedTrainingProgram->id);

    expect($libraryBackedTrainingProgram->exercise_program_id)->not->toBe($libraryProgram->id)
        ->and($libraryBackedTrainingProgram->program->parent_id)->toBe($libraryBackedTrainingProgram->id)
        ->and($libraryProgram->fresh()->parent_id)->toBeNull()
        ->and($libraryProgram->fresh()->parent_type)->toBeNull();

    expect(ExerciseProgram::query()->find($orphanedScheduledProgram->id))->toBeNull()
        ->and(ExerciseProgram::withTrashed()->find($orphanedScheduledProgram->id))->not->toBeNull();
});
