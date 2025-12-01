<?php

namespace Tests\Feature\Training\Progression;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;

trait ProgressionTestCase
{
    protected function createTestTree(): TrainingTree
    {
        TrainingNode::clearRegistry();

        $weeks = [];
        for ($i = 0; $i < 5; $i++) {
            $weeks[] = new WeekData;
        }

        $blockData = (new BlockData)->withChildren($weeks);
        $blockNode = TrainingNode::fromData($blockData);
        $blockNode->name = 'Test Block';

        $tree = TrainingTree::fromTrainingNode($blockNode);

        $firstWeek = $tree->root->children[0];
        $tree->executeAction('session.add', [
            'parentId' => $firstWeek->uuid,
            'day' => 0,
            'slot' => 0,
            'category' => 1,
            'exercises' => [1],
            'name' => 'Test Session',
        ]);

        for ($i = 1; $i < 5; $i++) {
            $tree->root->children[$i] = $firstWeek->createLinkedClone(
                $tree->root->uuid,
                $i
            );
        }

        return $tree;
    }

    protected function createSessionMap(TrainingTree $tree): array
    {
        $sessionMap = [];
        $sourceWeek = $tree->root->children[0];
        $sourceSessions = [];

        foreach ($sourceWeek->getChildren() as $session) {
            $sourceSessions[$session->uuid] = [
                'day' => $session->getData()->day,
                'slot' => $session->getData()->slot,
            ];
        }

        foreach ($tree->root->getChildren() as $weekIndex => $week) {
            $sessionMap[$weekIndex] = $sourceSessions;
        }

        return $sessionMap;
    }

    protected function createDefaultConfig(): ProgressionConfig
    {
        return new ProgressionConfig(
            weightStrategy: 'fixed_step',
            repStrategy: 'paired_ladder',
            targetImprovement: 0.125,
            startingReps: 12,
            stepDownInterval: 2,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
        );
    }

    protected function createDefaultAthleteData(): AthleteData
    {
        return new AthleteData(
            athleteId: 1,
            tests: [
                1 => new AthleteTest(
                    exerciseId: 1,
                    reps: 8,
                    weight: 56.0,
                ),
            ],
        );
    }

    protected function createDefaultOverrideStore(): OverrideStore
    {
        return new OverrideStore;
    }
}
