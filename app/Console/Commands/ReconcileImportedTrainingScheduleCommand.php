<?php

namespace App\Console\Commands;

use App\Support\Import\ImportedTrainingScheduleReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ReconcileImportedTrainingScheduleCommand extends Command
{
    public const SIGNATURE = 'data:reconcile-imported-training-schedule
        {dump? : Optional path to a local .sql or .sql.gz dump}';

    protected $signature = self::SIGNATURE;

    protected $description = 'Restores missing scheduled training rows from a local import dump when post-import state is incomplete.';

    public function handle(ImportedTrainingScheduleReconciler $reconciler): int
    {
        $dumpPath = $this->resolveDumpPath();

        if ($dumpPath === null) {
            return self::FAILURE;
        }

        $result = $reconciler->reconcile($dumpPath);

        $this->info('Imported training schedule reconciliation complete.');
        $this->line('Missing training programs detected: '.$result['missing_training_programs']);
        $this->line('Exercise programs restored: '.$result['restored_exercise_programs']);
        $this->line('Program exercises restored: '.$result['restored_program_exercises']);
        $this->line('Training programs restored: '.$result['restored_training_programs']);
        $this->line('Blocks restored: '.$result['restored_blocks']);
        $this->line('Slots restored: '.$result['restored_slots']);
        $this->line('Slot exercises restored: '.$result['restored_slot_exercises']);
        $this->line('Slot sets restored: '.$result['restored_slot_sets']);
        $this->line('Slot values restored: '.$result['restored_slot_values']);
        $this->line('Metric submissions restored: '.$result['restored_metric_submissions']);
        $this->line('Metric values restored: '.$result['restored_metric_values']);
        $this->line('Program configs normalized: '.$result['normalized_program_configs']);

        if ($result['affected_groups'] !== []) {
            $this->line('Affected groups: '.implode(', ', $result['affected_groups']));
        }

        return self::SUCCESS;
    }

    private function resolveDumpPath(): ?string
    {
        $dumpArgument = $this->argument('dump');

        if (is_string($dumpArgument) && $dumpArgument !== '') {
            $path = str_starts_with($dumpArgument, DIRECTORY_SEPARATOR)
                ? $dumpArgument
                : base_path($dumpArgument);

            if (! is_file($path)) {
                $this->error("Dump file not found: {$path}");

                return null;
            }

            return $path;
        }

        $paths = File::glob(base_path('import/dumps/*.sql')) ?: [];
        $gzPaths = File::glob(base_path('import/dumps/*.sql.gz')) ?: [];
        $dumps = array_merge($paths, $gzPaths);

        if ($dumps === []) {
            $this->error('No local dump found in import/dumps.');

            return null;
        }

        usort($dumps, fn (string $left, string $right) => filemtime($right) <=> filemtime($left));

        return $dumps[0];
    }
}
