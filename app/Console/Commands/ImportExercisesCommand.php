<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseEquipment;
use App\Models\Exercise\ExerciseMuscle;
use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
use App\Models\Exercise\Types\StrengthExercise;
use App\Models\Exercise\Types\PlyometricExercise;
use App\Models\Exercise\Types\StretchingExercise;
use App\Models\Exercise\Types\CardioExercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportExercisesCommand extends Command
{
    protected $signature = 'exercise:import';

    protected $description = 'Import exercises from JSON files in import/exercises directory';

    private array $stats = [
        'equipment' => ['created' => 0, 'existing' => 0],
        'muscles' => ['created' => 0, 'existing' => 0],
        'exercises' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
    ];

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

        $this->info("Found " . count($files) . " exercise files to import...\n");

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $this->importExercise($file);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayStats();

        return 0;
    }

    private function importExercise(string $filePath): void
    {
        $content = File::get($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->stats['exercises']['skipped']++;
            return;
        }

        // Import related entities first
        $equipmentIds = $this->importEquipment($data['equipment'] ?? null);
        $primaryMuscleIds = $this->importMuscles($data['primaryMuscles'] ?? []);
        $secondaryMuscleIds = $this->importMuscles($data['secondaryMuscles'] ?? []);

        // Determine exercise type/class
        $exerciseClass = $this->getExerciseClass($data['category'] ?? null);

        // Create or update exercise
        $name = $data['name'] ?? $data['id'];

        $exercise = $exerciseClass::updateOrCreate(
            ['name' => $name],
            [
                'level' => $this->getEnumValue(Level::class, $data['level'] ?? null),
                'mechanic' => $this->getEnumValue(Mechanic::class, $data['mechanic'] ?? null),
                'instructions' => $data['instructions'] ?? null,
            ]
        );

        if ($exercise->wasRecentlyCreated) {
            $this->stats['exercises']['created']++;
        } else {
            $this->stats['exercises']['updated']++;
        }

        // Sync equipment relationships
        if (!empty($equipmentIds)) {
            $exercise->equipment()->sync($equipmentIds);
        }

        // Sync muscle relationships
        if (!empty($primaryMuscleIds)) {
            $exercise->primaryMuscles()->sync($primaryMuscleIds);
        }

        if (!empty($secondaryMuscleIds)) {
            $exercise->secondaryMuscles()->sync($secondaryMuscleIds);
        }
    }

    private function importEquipment(string|array|null $equipment): array
    {
        if (!$equipment) {
            return [];
        }

        // Handle both string (single equipment) and array (multiple equipment)
        $equipmentList = is_array($equipment) ? $equipment : [$equipment];
        $ids = [];

        foreach ($equipmentList as $equipmentName) {
            if (empty($equipmentName)) {
                continue;
            }

            $model = ExerciseEquipment::firstOrCreate(
                ['name' => $equipmentName]
            );

            if ($model->wasRecentlyCreated) {
                $this->stats['equipment']['created']++;
            } else {
                $this->stats['equipment']['existing']++;
            }

            $ids[] = $model->id;
        }

        return $ids;
    }

    private function importMuscles(array $muscles): array
    {
        $ids = [];

        foreach ($muscles as $muscle) {
            if (empty($muscle)) {
                continue;
            }

            $model = ExerciseMuscle::firstOrCreate(
                ['name' => $muscle]
            );

            if ($model->wasRecentlyCreated) {
                $this->stats['muscles']['created']++;
            } else {
                $this->stats['muscles']['existing']++;
            }

            $ids[] = $model->id;
        }

        return $ids;
    }

    private function getExerciseClass(?string $category): string
    {
        if (!$category) {
            return StrengthExercise::class;
        }

        // Map category strings to exercise classes
        $categoryMap = [
            'strength' => StrengthExercise::class,
            'plyometrics' => PlyometricExercise::class,
            'stretching' => StretchingExercise::class,
            'cardio' => CardioExercise::class,
            'powerlifting' => StrengthExercise::class,
            'strongman' => StrengthExercise::class,
            'olympic weightlifting' => StrengthExercise::class,
        ];

        $categoryLower = strtolower($category);

        return $categoryMap[$categoryLower] ?? StrengthExercise::class;
    }

    private function getEnumValue(string $enumClass, ?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // Try to match enum case
        foreach ($enumClass::cases() as $case) {
            if (strtolower($case->value) === strtolower($value)) {
                return $case->value;
            }
        }

        return null;
    }

    private function displayStats(): void
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('IMPORT COMPLETE');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        $this->line('Equipment:');
        $this->line("  Created: {$this->stats['equipment']['created']}");
        $this->line("  Existing: {$this->stats['equipment']['existing']}");
        $this->newLine();

        $this->line('Muscles:');
        $this->line("  Created: {$this->stats['muscles']['created']}");
        $this->line("  Existing: {$this->stats['muscles']['existing']}");
        $this->newLine();

        $this->line('Exercises:');
        $this->line("  Created: {$this->stats['exercises']['created']}");
        $this->line("  Updated: {$this->stats['exercises']['updated']}");
        $this->line("  Skipped: {$this->stats['exercises']['skipped']}");
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════════════');
    }
}
