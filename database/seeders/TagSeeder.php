<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMuscleGroups();
        $this->seedEquipment();
    }

    protected function seedMuscleGroups(): void
    {
        $tags = [
            'Chest',
            'Back',
            'Shoulders',
            'Quadriceps',
            'Hamstrings',
            'Glutes',
            'Biceps',
            'Triceps',
            'Core',
            'Calves',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate(
                ['name' => $name, 'scope' => 'muscle_group'],
            );
        }
    }

    protected function seedEquipment(): void
    {
        $tags = [
            'Barbell',
            'Dumbbell',
            'Kettlebell',
            'Cable Machine',
            'Bodyweight',
            'Resistance Band',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate(
                ['name' => $name, 'scope' => 'equipment'],
            );
        }
    }
}
