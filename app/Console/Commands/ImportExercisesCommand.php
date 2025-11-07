<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseCategory;
use App\Models\Exercise\ExerciseEquipment;
use App\Models\Exercise\ExerciseMuscle;
use App\Models\Exercise\Force;
use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
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
        'categories' => ['created' => 0, 'existing' => 0],
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
        $equipmentId = $this->importEquipment($data['equipment'] ?? null);
        $categoryId = $this->importCategory($data['category'] ?? null);
        $primaryMuscleIds = $this->importMuscles($data['primaryMuscles'] ?? []);
        $secondaryMuscleIds = $this->importMuscles($data['secondaryMuscles'] ?? []);

        // Create or update exercise
        $slug = Str::slug($data['name'] ?? $data['id']);

        $exercise = Exercise::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'] ?? $data['id'],
                'force' => $this->getEnumValue(Force::class, $data['force'] ?? null),
                'level' => $this->getEnumValue(Level::class, $data['level'] ?? null),
                'mechanic' => $this->getEnumValue(Mechanic::class, $data['mechanic'] ?? null),
                'exercise_equipment_id' => $equipmentId,
                'exercise_category_id' => $categoryId,
                'instructions' => $data['instructions'] ?? null,
            ]
        );

        if ($exercise->wasRecentlyCreated) {
            $this->stats['exercises']['created']++;
        } else {
            $this->stats['exercises']['updated']++;
        }

        // Sync muscle relationships
        if (!empty($primaryMuscleIds)) {
            $exercise->primaryMuscles()->sync($primaryMuscleIds);
        }

        if (!empty($secondaryMuscleIds)) {
            $exercise->secondaryMuscles()->sync($secondaryMuscleIds);
        }
    }

    private function importEquipment(?string $equipment): ?string
    {
        if (!$equipment) {
            return null;
        }

        $id = Str::slug($equipment);

        $model = ExerciseEquipment::firstOrCreate(
            ['id' => $id],
            ['name' => $equipment]
        );

        if ($model->wasRecentlyCreated) {
            $this->stats['equipment']['created']++;
        } else {
            $this->stats['equipment']['existing']++;
        }

        return $id;
    }

    private function importCategory(?string $category): ?string
    {
        if (!$category) {
            return null;
        }

        $id = Str::slug($category);

        $model = ExerciseCategory::firstOrCreate(
            ['id' => $id],
            ['name' => $category]
        );

        if ($model->wasRecentlyCreated) {
            $this->stats['categories']['created']++;
        } else {
            $this->stats['categories']['existing']++;
        }

        return $id;
    }

    private function importMuscles(array $muscles): array
    {
        $ids = [];

        foreach ($muscles as $muscle) {
            if (empty($muscle)) {
                continue;
            }

            $id = Str::slug($muscle);

            $model = ExerciseMuscle::firstOrCreate(
                ['id' => $id],
                ['name' => $muscle]
            );

            if ($model->wasRecentlyCreated) {
                $this->stats['muscles']['created']++;
            } else {
                $this->stats['muscles']['existing']++;
            }

            $ids[] = $id;
        }

        return $ids;
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

        $this->line('Categories:');
        $this->line("  Created: {$this->stats['categories']['created']}");
        $this->line("  Existing: {$this->stats['categories']['existing']}");
        $this->newLine();

        $this->line('Exercises:');
        $this->line("  Created: {$this->stats['exercises']['created']}");
        $this->line("  Updated: {$this->stats['exercises']['updated']}");
        $this->line("  Skipped: {$this->stats['exercises']['skipped']}");
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════════════');
    }
}
