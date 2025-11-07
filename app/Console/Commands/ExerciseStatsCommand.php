<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExerciseStatsCommand extends Command
{
    protected $signature = 'exercise:stats';

    protected $description = 'Analyze exercise JSON files and display aggregate metrics statistics';

    public function handle()
    {
        $exercisesPath = base_path('import/exercises');

        if (!File::isDirectory($exercisesPath)) {
            $this->error("Directory not found: {$exercisesPath}");
            return 1;
        }

        $files = File::glob($exercisesPath . '/*.json');

        if (empty($files)) {
            $this->error("No JSON files found in {$exercisesPath}");
            return 1;
        }

        $this->info("Analyzing " . count($files) . " exercise files...\n");

        // Initialize metrics arrays
        $metrics = [
            'force' => [],
            'level' => [],
            'mechanic' => [],
            'equipment' => [],
            'primaryMuscles' => [],
            'secondaryMuscles' => [],
            'category' => [],
        ];

        // Process each file
        foreach ($files as $file) {
            $content = File::get($file);
            $exercise = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("Skipping invalid JSON file: " . basename($file));
                continue;
            }

            // Collect scalar metrics
            foreach (['force', 'level', 'mechanic', 'equipment', 'category'] as $field) {
                if (isset($exercise[$field]) && $exercise[$field] !== null && $exercise[$field] !== '') {
                    $value = $exercise[$field];
                    $metrics[$field][$value] = ($metrics[$field][$value] ?? 0) + 1;
                }
            }

            // Collect array metrics (primaryMuscles, secondaryMuscles)
            foreach (['primaryMuscles', 'secondaryMuscles'] as $field) {
                if (isset($exercise[$field]) && is_array($exercise[$field])) {
                    foreach ($exercise[$field] as $muscle) {
                        if ($muscle !== null && $muscle !== '') {
                            $metrics[$field][$muscle] = ($metrics[$field][$muscle] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        // Display results
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->info('EXERCISE METRICS ANALYSIS');
        $this->line('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($metrics as $metricName => $values) {
            // Sort by frequency (descending)
            arsort($values);

            $uniqueCount = count($values);
            $totalCount = array_sum($values);

            $this->line("─────────────────────────────────────────────────────────────────");
            $this->info(strtoupper($metricName));
            $this->line("─────────────────────────────────────────────────────────────────");
            $this->line("Unique values: {$uniqueCount} | Total occurrences: {$totalCount}");
            $this->newLine();

            if (empty($values)) {
                $this->comment("  No data available");
            } else {
                // Display all unique values sorted by frequency
                $maxValueLength = max(array_map('strlen', array_keys($values)));

                foreach ($values as $value => $count) {
                    $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0;
                    $bar = str_repeat('█', min(50, (int)($percentage / 2)));

                    $this->line(sprintf(
                        "  %-{$maxValueLength}s : %4d (%5.1f%%) %s",
                        $value,
                        $count,
                        $percentage,
                        $bar
                    ));
                }
            }

            $this->newLine();
        }

        $this->line('═══════════════════════════════════════════════════════════════');
        $this->info('Analysis complete!');

        return 0;
    }
}
