<?php

namespace Database\Seeders;

use App\Models\Exercise\ExerciseTemplate;
use Illuminate\Database\Seeder;

class ExerciseTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::templates() as $name => $config) {
            ExerciseTemplate::firstOrCreate(
                ['name' => $name],
                ['config' => $config],
            );
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function templates(): array
    {
        $base = [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'overrides' => ['cells' => [], 'weeks' => []],
        ];

        return [
            'Strength (Automatic)' => array_replace_recursive($base, [
                'weight' => ['default' => 0],
            ]),
            'Strength (Manual)' => array_replace_recursive($base, [
                'weight' => ['mode' => 'manual'],
            ]),
        ];
    }
}
