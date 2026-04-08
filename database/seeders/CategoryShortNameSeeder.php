<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class CategoryShortNameSeeder extends Seeder
{
    private const SHORT_NAMES = [
        'Coordination' => 'CDN',
        'Core' => 'COR',
        'Endurance' => 'END',
        'Mobility' => 'MOB',
        'Recovery' => 'REC',
        'Rehab' => 'RHB',
        'Ski' => 'SKI',
        'Strength' => 'STR',
        'Velocity' => 'VEL',
        'Warm Up' => 'WRM',
    ];

    public function run(): void
    {
        foreach (self::SHORT_NAMES as $name => $shortName) {
            Tag::query()
                ->where('scope', 'exercise_category')
                ->where('name', $name)
                ->whereNull('short_name')
                ->update(['short_name' => $shortName]);
        }
    }
}
