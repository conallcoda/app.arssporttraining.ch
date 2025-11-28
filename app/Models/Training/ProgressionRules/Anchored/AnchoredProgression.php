<?php

namespace App\Models\Training\ProgressionRules\Anchored;

use App\Models\Training\ProgressionRules\AbstractProgressionRuleset;
use App\Models\Training\TrainingTree;

class AnchoredProgression extends AbstractProgressionRuleset
{
    public array $metrics = [];

    public function __construct(
        public float $start = 50,
        public float $increase = 7.5,
        public float $increaseStep = 0.5,
    ) {}

    public function apply(TrainingTree $tree): TrainingTree
    {
        $rules = [
            new SetBlockAnchor(),
            new AllExercisesWithinSameWeekAreTreatedEqually(),
        ];

        foreach ($rules as $rule) {
            $result = $rule->apply($tree, $this);
            $this->metrics = array_merge($this->metrics, $result);
        }

        return $tree;
    }
}
