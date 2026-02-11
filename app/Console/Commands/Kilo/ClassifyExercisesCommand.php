<?php

namespace App\Console\Commands\Kilo;

use Illuminate\Console\Command;
use Symfony\Component\VarExporter\VarExporter;

class ClassifyExercisesCommand extends Command
{
    protected $signature = 'kilo:classify {--force : Overwrite existing classified file}';

    protected $description = 'Classify exercises from the Kilo PDF export by exercise type';

    public function handle(ExerciseClassifier $classifier): int
    {
        $inputPath = base_path('import/kilo/exercises.php');

        if (! file_exists($inputPath)) {
            $this->error("Exercises file not found at: {$inputPath}");
            $this->error('Run php artisan kilo:parse first.');

            return 1;
        }

        $outputPath = base_path('import/kilo/classified_exercises.php');

        if (file_exists($outputPath) && ! $this->option('force')) {
            $this->error("Output file already exists: {$outputPath}");
            $this->error('Use --force to overwrite.');

            return 1;
        }

        $data = require $inputPath;
        $exercises = $data['exercises'] ?? [];

        $this->info('Classifying '.count($exercises).' exercises...');
        $this->newLine();

        $classified = [];
        $stats = [
            'by_type' => [],
            'by_confidence' => [],
        ];

        foreach ($exercises as $exercise) {
            $result = $classifier->classify($exercise);

            $classified[] = array_merge($exercise, [
                'type' => $result['type'],
                'confidence' => $result['confidence'],
                'reason' => $result['reason'],
                'override' => null,
            ]);

            $stats['by_type'][$result['type']] = ($stats['by_type'][$result['type']] ?? 0) + 1;
            $stats['by_confidence'][$result['confidence']] = ($stats['by_confidence'][$result['confidence']] ?? 0) + 1;
        }

        $exported = VarExporter::export([
            'classified_at' => now()->toIso8601String(),
            'stats' => [
                'total' => count($classified),
                'by_type' => $stats['by_type'],
                'by_confidence' => $stats['by_confidence'],
            ],
            'exercises' => $classified,
        ]);

        file_put_contents($outputPath, "<?php\n\nreturn {$exported};\n");

        $this->displayStats($stats, count($classified));
        $this->displayReviewItems($classified);
        $this->newLine();
        $this->info("Output: {$outputPath}");

        return 0;
    }

    private function displayStats(array $stats, int $total): void
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('CLASSIFICATION COMPLETE');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $typeLabels = [
            'strength_automatic' => 'Strength (Automatic)',
            'strength_manual' => 'Strength (Manual)',
            'conditioning' => 'Conditioning',
            'timed' => 'Timed',
        ];

        $rows = [];
        foreach ($typeLabels as $type => $label) {
            $rows[] = [$label, $stats['by_type'][$type] ?? 0];
        }

        $this->table(['Type', 'Count'], $rows);
        $this->newLine();

        $this->line("  Total: {$total}");
        $this->line('  High confidence: '.($stats['by_confidence']['high'] ?? 0));
        $this->line('  Medium confidence: '.($stats['by_confidence']['medium'] ?? 0));
        $this->line('  Low confidence: '.($stats['by_confidence']['low'] ?? 0));
    }

    private function displayReviewItems(array $classified): void
    {
        $reviewItems = array_filter($classified, fn (array $e) => $e['confidence'] !== 'high');

        if (empty($reviewItems)) {
            return;
        }

        $this->newLine();
        $this->warn('Items needing review ('.count($reviewItems).'):');
        $this->newLine();

        $rows = [];
        foreach ($reviewItems as $item) {
            $rows[] = [
                $item['full_name'],
                $item['type'],
                $item['confidence'],
                $item['reason'],
                implode(', ', $item['categories']),
            ];
        }

        $this->table(['Exercise', 'Type', 'Confidence', 'Reason', 'Categories'], $rows);
    }
}
