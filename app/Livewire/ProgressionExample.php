<?php

namespace App\Livewire;

use App\Models\Exercise\Exercise;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Result\ExerciseProgression;
use App\Models\Training\Progression\Strategy\ProgressionCalculator;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProgressionExample extends Component
{
    public ?TrainingTree $tree = null;

    public ProgressionConfig $progressionConfig;

    public OverrideStore $overrideStore;

    public AthleteData $athleteData;

    protected int $defaultWeeks = 4;

    public int $exerciseId = 1;

    public function boot(): void
    {
        $this->progressionConfig ??= $this->createDefaultProgressionConfig();
        $this->overrideStore ??= new OverrideStore;
        $this->athleteData ??= $this->createDefaultAthleteData();
    }

    public function mount(): void
    {
        $this->initializeTree();
    }

    protected function createDefaultAthleteData(): AthleteData
    {
        $athleteData = new AthleteData(athleteId: 1);
        $athleteData->setTest(new AthleteTest(
            exerciseId: $this->exerciseId,
            reps: 8,
            weight: 45.0,
        ));

        return $athleteData;
    }

    protected function createDefaultProgressionConfig(): ProgressionConfig
    {
        return new ProgressionConfig(
            blockLength: $this->defaultWeeks,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new PairedLadderRepConfig(
                startingReps: 12,
                stepDownInterval: 2,
                repDecrement: 2,
                minimumReps: 8,
            ),
        );
    }

    protected function initializeTree(): void
    {
        TrainingNode::clearRegistry();

        $weeks = [];

        for ($weekIndex = 0; $weekIndex < $this->defaultWeeks; $weekIndex++) {
            $weeks[] = new WeekData;
        }

        $blockData = (new BlockData)->withChildren($weeks);
        $blockNode = TrainingNode::fromData($blockData);
        $blockNode->name = 'Example Block';

        $firstWeekNode = $blockNode->children[0] ?? null;

        if ($firstWeekNode) {
            foreach ($blockNode->children as $weekIndex => $_) {
                if ($weekIndex === 0) {
                    continue;
                }
                $blockNode->children[$weekIndex] = $firstWeekNode->createLinkedClone($blockNode->uuid, $weekIndex);
            }
        }

        $this->tree = TrainingTree::fromTrainingNode($blockNode);

        $this->addGymSession();
    }

    protected function addGymSession(): void
    {
        $firstWeek = $this->tree->root->children[0] ?? null;

        if (! $firstWeek) {
            return;
        }

        $event = $this->tree->executeAction('session.add', [
            'parentId' => $firstWeek->uuid,
            'day' => 0,
            'slot' => 0,
            'category' => 1,
            'exercises' => [$this->exerciseId],
            'name' => 'Gym 1A',
        ]);

        $sourceSession = $event->child ?? null;
        if ($sourceSession) {
            $this->tree->executeAction('session.add', [
                'parentId' => $firstWeek->uuid,
                'day' => 2,
                'slot' => 0,
                'linkId' => $sourceSession->uuid,
            ]);
        }
    }

    public function buildSessionMap(): array
    {
        if (! $this->tree) {
            return [];
        }

        $sessionMap = [];
        $sourceWeek = collect($this->tree->root->getChildren())->first(fn($week) => ! $week->isLinked());

        if (! $sourceWeek) {
            return [];
        }

        $sourceSessions = [];
        foreach ($sourceWeek->getChildren() as $session) {
            $sourceSessions[$session->uuid] = [
                'day' => $session->getData()->day,
                'slot' => $session->getData()->slot,
            ];
        }

        foreach ($this->tree->root->getChildren() as $weekIndex => $week) {
            $sessionMap[$weekIndex] = $sourceSessions;
        }

        return $sessionMap;
    }

    #[Computed]
    public function exerciseProgression(): ?ExerciseProgression
    {
        if (! $this->tree) {
            return null;
        }

        $sessionMap = $this->buildSessionMap();
        $calculator = new ProgressionCalculator(
            $this->progressionConfig,
            $this->overrideStore,
            $this->athleteData,
        );

        return $calculator->calculateExerciseProgression($this->exerciseId, $sessionMap);
    }

    #[Computed]
    public function exercise(): ?Exercise
    {
        return Exercise::find($this->exerciseId);
    }

    #[Computed]
    public function exerciseRows(): array
    {
        $progression = $this->exerciseProgression;
        $rows = [];

        if (! $this->tree || ! $progression) {
            return [];
        }

        foreach ($this->tree->root->getChildren() as $weekIndex => $week) {
            $resolvedWeek = $week->isLinked()
                ? collect($this->tree->root->getChildren())->first(fn($w) => ! $w->isLinked())
                : $week;

            if (! $resolvedWeek) {
                continue;
            }

            $sessionNumber = 0;
            foreach ($resolvedWeek->getChildren() as $session) {
                $sessionNumber++;
                $resolvedSession = $session->isLinked()
                    ? $this->findSessionByUuid($session->linked_to)
                    : $session;

                $exerciseIds = $resolvedSession?->getData()->exercises ?? [];

                if (! in_array($this->exerciseId, $exerciseIds)) {
                    continue;
                }

                $reps = [];
                $weights = [];
                $sources = [];

                $weekResult = $progression->weeks[$weekIndex] ?? null;
                $sessionResult = $weekResult?->getSession($session->uuid);

                if ($sessionResult) {
                    foreach ($sessionResult->sets as $set) {
                        $reps[] = $set->reps;
                        $weights[] = $set->weight;
                        $sources[] = $set->source;
                    }
                }

                $rows[] = [
                    'week' => $weekIndex + 1,
                    'weekIndex' => $weekIndex,
                    'weekUuid' => $week->uuid,
                    'session' => $sessionNumber,
                    'sessionUuid' => $session->uuid,
                    'sessionDay' => $session->getData()->day,
                    'sessionSlot' => $session->getData()->slot,
                    'reps' => $reps,
                    'weights' => $weights,
                    'sources' => $sources,
                    'anchorWeight' => $weekResult?->anchorWeight,
                    'anchorReps' => $weekResult?->anchorReps,
                    'projected1RM' => $weekResult?->projected1RM,
                    'derived1RM' => $weekResult?->getDerived1RM(),
                ];
            }
        }

        return $rows;
    }

    #[Computed]
    public function setCount(): int
    {
        $startingReps = $this->progressionConfig->repConfig->startingReps;

        return $startingReps >= 12 ? 4 : 3;
    }

    protected function findSessionByUuid(string $uuid): ?TrainingNode
    {
        if (! $this->tree) {
            return null;
        }

        foreach ($this->tree->root->getChildren() as $week) {
            foreach ($week->getChildren() as $session) {
                if ($session->uuid === $uuid) {
                    return $session;
                }
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.progression-example');
    }
}
