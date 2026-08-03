<?php

use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps the newest manual and automatic 1RM on the same athlete date', function () {
    $athlete = User::factory()->athlete()->create();
    $date = '2026-08-01';

    $olderManual = insertOneRepMaxSubmission($athlete->id, $date, User::class, 10, '2026-08-01 10:00:00');
    $newerManual = insertOneRepMaxSubmission($athlete->id, $date, User::class, 11, '2026-08-01 11:00:00');
    $olderAutomatic = insertOneRepMaxSubmission($athlete->id, $date, TrainingProgramBlock::class, 20, '2026-08-01 09:00:00');
    $newerAutomatic = insertOneRepMaxSubmission($athlete->id, $date, TrainingProgramBlock::class, 21, '2026-08-01 12:00:00');

    foreach ([$olderManual, $newerManual, $olderAutomatic, $newerAutomatic] as $submissionId) {
        DB::table('user_metric_values')->insert([
            'submission_id' => $submissionId,
            'field' => 'estimated1RM',
            'value' => (string) $submissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (require database_path('migrations/2026_08_03_120000_deduplicate_same_day_one_rep_max_submissions.php'))->up();

    $active = DB::table('user_metric_submissions')
        ->where('user_id', $athlete->id)
        ->where('metric', 'oneRepMax')
        ->whereDate('recorded_at', $date)
        ->whereNull('deleted_at')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($active)->toBe([$newerManual, $newerAutomatic])
        ->and(DB::table('user_metric_submissions')->whereIn('id', [$olderManual, $olderAutomatic])->whereNotNull('deleted_at')->count())->toBe(2)
        ->and(DB::table('user_metric_values')->whereIn('submission_id', [$olderManual, $newerManual, $olderAutomatic, $newerAutomatic])->count())->toBe(4);
});

it('uses the highest id as the deterministic duplicate tie breaker', function () {
    $athlete = User::factory()->athlete()->create();

    $first = insertOneRepMaxSubmission($athlete->id, '2026-08-01', null, null, '2026-08-01 12:00:00');
    $second = insertOneRepMaxSubmission($athlete->id, '2026-08-01', User::class, 7, '2026-08-01 10:00:00');

    (require database_path('migrations/2026_08_03_120000_deduplicate_same_day_one_rep_max_submissions.php'))->up();

    expect(DB::table('user_metric_submissions')->where('id', $first)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('user_metric_submissions')->where('id', $second)->value('deleted_at'))->toBeNull();
});

function insertOneRepMaxSubmission(
    int $userId,
    string $date,
    ?string $ownerType,
    ?int $ownerId,
    string $updatedAt,
): int {
    return DB::table('user_metric_submissions')->insertGetId([
        'user_id' => $userId,
        'metric' => 'oneRepMax',
        'recorded_by' => null,
        'recorded_at' => $date,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'created_at' => $updatedAt,
        'updated_at' => $updatedAt,
        'deleted_at' => null,
    ]);
}
