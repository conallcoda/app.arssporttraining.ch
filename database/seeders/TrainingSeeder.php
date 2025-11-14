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
                $block = new TrainingBlock([
                    'sequence' => $blockNum,
                ]);
                $block->appendToNode($season)->save();

                for ($weekNum = 0; $weekNum < $numWeeks; $weekNum++) {
                    $week = new TrainingWeek([
                        'sequence' => $weekNum,
                    ]);
                    $week->appendToNode($block)->save();
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
