<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BLOCK_OWNER = 'App\\Models\\Training\\TrainingProgramBlock';

    private const USER_OWNER = 'App\\Models\\Users\\User';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->deduplicate(function (Builder $query): void {
                $query->where(function (Builder $owners): void {
                    $owners->whereNull('owner_type')
                        ->orWhere('owner_type', self::USER_OWNER);
                });
            });

            $this->deduplicate(function (Builder $query): void {
                $query->where('owner_type', self::BLOCK_OWNER);
            });
        });
    }

    public function down(): void
    {
        // The discarded rows remain soft deleted, but choosing which rows to
        // restore automatically would reintroduce the invalid duplicates.
    }

    /**
     * @param  callable(Builder): void  $constrainKind
     */
    private function deduplicate(callable $constrainKind): void
    {
        $duplicates = DB::table('user_metric_submissions')
            ->where('metric', 'oneRepMax')
            ->whereNull('deleted_at');

        $constrainKind($duplicates);

        $duplicates
            ->select(['user_id', 'recorded_at'])
            ->groupBy('user_id', 'recorded_at')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')
            ->orderBy('recorded_at')
            ->get()
            ->each(function (object $duplicate) use ($constrainKind): void {
                $submissions = DB::table('user_metric_submissions')
                    ->where('metric', 'oneRepMax')
                    ->where('user_id', $duplicate->user_id)
                    ->whereDate('recorded_at', $duplicate->recorded_at)
                    ->whereNull('deleted_at');

                $constrainKind($submissions);

                $discardedIds = $submissions
                    ->orderByDesc('id')
                    ->pluck('id')
                    ->slice(1)
                    ->values();

                if ($discardedIds->isEmpty()) {
                    return;
                }

                $now = now();

                DB::table('user_metric_submissions')
                    ->whereIn('id', $discardedIds)
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
            });
    }
};
