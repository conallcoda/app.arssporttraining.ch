<?php

use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('restores missing scheduled training rows from a dump', function () {
    $owner = User::factory()->create();
    $athlete = User::factory()->athlete()->create(['owner_id' => $owner->id]);

    $group = UserGroup::create([
        'owner_id' => $owner->id,
        'name' => 'Armando',
    ]);

    $group->members()->attach($athlete->id);

    DB::table('exercise_programs')->insert([
        'id' => 114,
        'owner_id' => null,
        'name' => 'Bali Programm Armando',
        'type' => 'program',
        'exercise_category_id' => null,
        'warm_up_program_id' => null,
        'warm_down_program_id' => null,
        'sort' => 0,
        'config' => '{"exercises":[],"userExercises":[],"weeks":5}',
        'parent_type' => null,
        'parent_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    $dumpPath = base_path('tests/Fixtures/tmp.reconcile-training.sql');

    File::put($dumpPath, implode("\n", [
        reconcileBuildDumpInsert('exercise_programs', [[
            'id' => 114,
            'owner_id' => null,
            'parent_type' => 'App\\Models\\Training\\TrainingProgram',
            'parent_id' => 10,
            'name' => 'Bali Programm Armando',
            'type' => 'program',
            'exercise_category_id' => null,
            'warm_up_program_id' => null,
            'warm_down_program_id' => null,
            'sort' => 0,
            'config' => '{"exercises":[],"userExercises":[],"weeks":5}',
            'created_at' => '2026-05-09 02:02:50',
            'updated_at' => '2026-05-13 07:54:49',
            'deleted_at' => null,
        ]]),
        reconcileBuildDumpInsert('exercise_program_exercises', []),
        reconcileBuildDumpInsert('training_programs', [[
            'id' => 10,
            'owner_id' => null,
            'group_id' => $group->id,
            'exercise_program_id' => 114,
            'sort' => -999999,
            'status' => null,
            'created_at' => '2026-05-09 02:10:22',
            'updated_at' => '2026-05-09 02:10:22',
            'deleted_at' => null,
        ]]),
        reconcileBuildDumpInsert('training_program_blocks', [[
            'id' => 6,
            'parent_id' => null,
            'owner_id' => null,
            'group_id' => $group->id,
            'user_id' => null,
            'category_id' => null,
            'type' => 'category',
            'start' => '2026-05-09',
            'end' => '2026-06-08',
            'note' => 'BLOCCK 1 STR',
            'color' => null,
            'config' => '{"goal":5,"autoRecord1rm":true}',
            'active' => 1,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => '2026-05-09 02:07:54',
            'updated_at' => '2026-05-09 02:07:54',
            'deleted_at' => null,
        ]]),
        reconcileBuildDumpInsert('training_program_slots', [[
            'id' => 187,
            'owner_id' => null,
            'user_id' => $athlete->id,
            'training_program_id' => 10,
            'datetime' => '2026-05-09 08:00:00',
            'scheduled_date' => '2026-05-09',
            'status' => 'pending',
            'compiled_at' => '2026-05-09 02:22:16',
            'compiled_version' => 'abc123',
            'exercise_count' => 1,
            'completed_exercise_count' => 0,
            'partial_exercise_count' => 0,
            'skipped_exercise_count' => 0,
            'pending_exercise_count' => 1,
            'has_any_modification' => 0,
            'completed_at' => null,
            'cancelled_at' => null,
            'created_at' => '2026-05-09 02:22:16',
            'updated_at' => '2026-05-09 02:22:16',
        ]]),
        reconcileBuildDumpInsert('training_program_slot_exercises', []),
        reconcileBuildDumpInsert('training_program_slot_sets', []),
        reconcileBuildDumpInsert('training_program_slot_set_values', []),
    ]));

    $this->artisan('data:reconcile-imported-training-schedule', ['dump' => $dumpPath])
        ->assertExitCode(0)
        ->expectsOutputToContain('Missing training programs detected: 1');

    expect(DB::table('training_programs')->where('group_id', $group->id)->count())->toBe(1)
        ->and(DB::table('training_program_blocks')->where('group_id', $group->id)->count())->toBe(1)
        ->and(DB::table('training_program_slots')->where('training_program_id', 10)->count())->toBe(1);

    File::delete($dumpPath);
});

function reconcileBuildDumpInsert(string $table, array $rows): string
{
    if ($rows === []) {
        return "INSERT INTO `{$table}` VALUES\n/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;\n";
    }

    $columns = Schema::getColumnListing($table);
    $lines = ["INSERT INTO `{$table}` VALUES"];

    foreach ($rows as $index => $row) {
        $values = [];

        foreach ($columns as $column) {
            $values[] = reconcileSqlDumpLiteral($row[$column] ?? null);
        }

        $suffix = $index === array_key_last($rows) ? ';' : ',';
        $lines[] = '('.implode(',', $values).')'.$suffix;
    }

    $lines[] = "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;";

    return implode("\n", $lines)."\n";
}

function reconcileSqlDumpLiteral(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $escaped = addcslashes((string) $value, "\\'");

    return "'{$escaped}'";
}
