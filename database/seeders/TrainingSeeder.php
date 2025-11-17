<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\Periods\TrainingBlock;
use App\Models\Training\Periods\TrainingWeek;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Example Training Plan',
                'format' => '2x5',
            ]
        ];

        foreach ($plans as $index => $planData) {
            [$numBlocks, $numWeeks] = $this->parseFormat($planData['format']);
            $season = TrainingSeason::create([
                'name' => $planData['name'],
                'sequence' => $index,
            ]);

            for ($blockNum = 0; $blockNum < $numBlocks; $blockNum++) {
                $block = TrainingBlock::create([
                    'parent_id' => $season->id,
                    'sequence' => $blockNum,
                ]);

                for ($weekNum = 0; $weekNum < $numWeeks; $weekNum++) {
                    TrainingWeek::create([
                        'parent_id' => $block->id,
                        'sequence' => $weekNum,
                    ]);
                }
            }
        }
    }

    private function parseFormat(string $format): array
    {
        if (!preg_match('/^(\d+)x(\d+)$/', $format, $matches)) {
            throw new \InvalidArgumentException("Invalid format: {$format}. Expected format like '2x5'");
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
