<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Training\Data;
use App\Models\Training\TrainingNode;
use Illuminate\Console\Command;

class ImportTrainingPlansCommand extends Command
{
    protected $signature = 'training:import';

    public static function getTemplate()
    {
        $e1 = Exercise::find(61);
        $e2 = Exercise::find(62);
        $e3 = Exercise::find(63);
        $e4 = Exercise::find(64);

        $createWeek = function () use ($e1, $e2, $e3, $e4) {
            return Data\WeekData::from()
                ->withChildren([
                    Data\SessionData::from(
                        [
                            'day' => 0,
                            'slot' => 1,
                        ]
                    )
                        ->withChildren([
                            Data\ExerciseData::from(['exercise' => $e1->id]),
                            Data\ExerciseData::from(['exercise' => $e2->id]),
                        ]),
                    Data\SessionData::from(
                        [
                            'day' => 2,
                            'slot' => 0,
                        ]
                    )
                        ->withChildren([
                            Data\ExerciseData::from(['exercise' => $e3->id]),
                            Data\ExerciseData::from(['exercise' => $e4->id]),
                        ]),
                ]);
        };

        $createBlock = function () use ($createWeek) {
            return Data\BlockData::from()
                ->withChildren([
                    $createWeek(),
                    $createWeek(),
                    $createWeek(),
                    $createWeek(),
                    $createWeek(),
                ]);
        };
        $template = Data\SeasonData::from()
            ->withChildren([
                $createBlock(),
                $createBlock(),
            ]);

        return $template;
    }

    public function handle()
    {
        $template = self::getTemplate();
        $tree = TrainingNode::fromData($template);
        $tree->name = 'Example Training Plan';

        $tree->save();

        return 0;
    }
}
