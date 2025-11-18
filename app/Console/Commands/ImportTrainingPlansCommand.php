<?php

namespace App\Console\Commands;


use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingSessionCategory;
use Illuminate\Console\Command;
use App\Models\Training\Data;
use App\Models\Training\TrainingNode;

class ImportTrainingPlansCommand extends Command
{
    protected $signature = 'training:import';


    public static function getTemplate()
    {
        $e1 = Exercise::find(61);
        $e2 = Exercise::find(62);
        $e3 = Exercise::find(63);
        $e4 = Exercise::find(64);

        $gym = TrainingSessionCategory::where('slug', 'gym')->first();

        $template =  Data\SeasonData::from()
            ->withChildren([
                Data\BlockData::from()
                    ->withChildren([
                        Data\WeekData::from()
                            ->withChildren([
                                Data\SessionData::from(
                                    [
                                        'category' => $gym->id,
                                        'day' => 0,
                                        'slot' => 1,
                                    ]
                                )
                                    ->withChildren([
                                        Data\ExerciseData::from(['exercise' => $e1->id]),
                                        Data\ExerciseData::from(['exercise' => $e2->id]),
                                    ])

                            ]),
                    ]),
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
