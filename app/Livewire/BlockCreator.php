<?php

namespace App\Livewire;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Override\SetOverride;
use App\Models\Training\Progression\Override\WeekAnchorOverride;
use App\Models\Training\Progression\Result\ExerciseProgression;
use App\Models\Training\Progression\Strategy\ProgressionCalculator;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Rep\ProportionalRepConfig;
use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class BlockCreator extends Component
{
    public ?TrainingTree $tree = null;

    public ProgressionConfig $progressionConfig;

    public OverrideStore $overrideStore;

    public AthleteData $athleteData;

    protected int $defaultWeeks = 5;

    protected string $storageKey = 'block-creator-tree';

    protected array $exerciseProgressions = [];

    public function boot(): void
    {
        $this->progressionConfig ??= $this->createDefaultProgressionConfig();
        $this->overrideStore ??= new OverrideStore;
        $this->athleteData ??= $this->createDefaultAthleteData();
    }

    protected function createDefaultAthleteData(): AthleteData
    {
        $athleteData = new AthleteData(athleteId: 0);
        $athleteData->setTest(new AthleteTest(
            exerciseId: 1,
            reps: 8,
            weight: 45.0,
        ));

        return $athleteData;
    }

    public function loadFromStorage(array $treeData, ?array $progressionData = null, ?array $overrideData = null)
    {
        TrainingNode::clearRegistry();
        $root = TrainingNode::from($treeData);
        $this->tree = TrainingTree::fromTrainingNode($root);

        if ($progressionData) {
            $this->progressionConfig = ProgressionConfig::from($progressionData);
        }

        if ($overrideData) {
            $this->overrideStore = OverrideStore::from($overrideData);
        }

        $this->syncProgressionBlockLength();
        $this->clearExerciseProgressions();
        $this->refreshGrid();
    }

    public function initializeWithDefaults()
    {
        $this->initializeTree();
    }

    public function clearStorage()
    {
        $this->overrideStore = new OverrideStore;
        $this->progressionConfig = $this->createDefaultProgressionConfig();
        $this->clearExerciseProgressions();
        $this->initializeTree();
    }

    protected function createDefaultProgressionConfig(): ProgressionConfig
    {
        return new ProgressionConfig(
            blockLength: $this->defaultWeeks,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 0.125,
                incrementStep: 0.5,
            ),
            repConfig: new PairedLadderRepConfig(
                startingReps: 12,
                stepDownInterval: 2,
                repDecrement: 2,
                minimumReps: 6,
            ),
        );
    }

    protected function syncProgressionBlockLength(): void
    {
        if ($this->tree) {
            $weekCount = count($this->tree->root->getChildren());
            if ($this->progressionConfig->blockLength !== $weekCount) {
                $this->progressionConfig->blockLength = $weekCount;
            }
        }
    }

    protected function initializeTree(): void
    {
        $weeks = [];

        for ($weekIndex = 0; $weekIndex < $this->defaultWeeks; $weekIndex++) {
            $weeks[] = new WeekData;
        }

        $blockData = (new BlockData)->withChildren($weeks);
        $blockNode = TrainingNode::fromData($blockData);
        $blockNode->name = 'New Block';

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

        $this->addDefaultSession();
        $this->refreshGrid();
    }

    protected function addDefaultSession(): void
    {
        $firstWeek = $this->tree->root->children[0] ?? null;

        if (! $firstWeek) {
            return;
        }

        $defaultCategory = \App\Models\Training\TrainingSessionCategory::first();
        $defaultExercises = \App\Models\Exercise\Exercise::take(3)->pluck('id')->toArray();

        if (! $defaultCategory) {
            return;
        }

        $event = $this->tree->executeAction('session.add', [
            'parentId' => $firstWeek->uuid,
            'day' => 0,
            'slot' => 0,
            'category' => $defaultCategory->id,
            'exercises' => $defaultExercises,
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

        $this->tree->executeAction('session.add', [
            'parentId' => $firstWeek->uuid,
            'day' => 4,
            'slot' => 1,
            'category' => 4,
            'exercises' => [6],
            'name' => null,
        ]);
    }

    #[Computed]
    public function nonLinkedSessions(): array
    {
        $sessions = [];
        $firstWeek = $this->tree->root->children[0] ?? null;

        if ($firstWeek) {
            foreach ($firstWeek->getChildren() as $session) {
                $sessions[] = [
                    'uuid' => $session->uuid,
                    'name' => $session->getData()->name,
                    'category' => $session->getData()->category,
                    'day' => $session->getData()->day,
                    'slot' => $session->getData()->slot,
                ];
            }
        }

        return $sessions;
    }

    #[On('schedule-action')]
    public function onScheduleAction(string $action, array $params)
    {
        logger()->info('Received schedule-action', ['action' => $action, 'params' => $params]);

        match ($action) {
            'session.add' => $this->addSession($params),
            'session.update' => $this->updateSession($params),
            'session.delete' => $this->deleteSession($params),
            'session.link' => $this->linkSession($params),
            'session.updateLink' => $this->updateSessionLink($params),
            'session.move' => $this->moveSession($params),
            'session.swap' => $this->swapSessions($params),
            'week.add' => $this->addWeek($params),
            'week.link' => $this->linkWeek($params),
            'week.delete' => $this->deleteWeek($params),
            default => null,
        };
    }

    #[On('progression-action')]
    public function onProgressionAction(string $action, array $params)
    {
        match ($action) {
            'set.override' => $this->setOverride($params),
            'set.clear-override' => $this->clearSetOverride($params),
            'week.anchor-override' => $this->setWeekAnchorOverride($params),
            'week.clear-anchor-override' => $this->clearWeekAnchorOverride($params),
            'config.update' => $this->updateProgressionConfig($params),
            default => null,
        };
    }

    #[On('progression-config-changed')]
    public function onProgressionConfigChanged(array $config)
    {
        if (isset($config['weightStrategy'])) {
            $this->progressionConfig->weightConfig = $this->createWeightConfig($config['weightStrategy']);
        }

        if (isset($config['repStrategy'])) {
            $this->progressionConfig->repConfig = $this->createRepConfig($config['repStrategy']);
        }

        $this->clearExerciseProgressions();
        $this->refreshGrid();
    }

    protected function createWeightConfig(string $strategy): FixedStepWeightConfig|CompoundedWeightConfig
    {
        $current = $this->progressionConfig->weightConfig;

        return match ($strategy) {
            'compounded' => new CompoundedWeightConfig(
                targetImprovement: $current->targetImprovement,
                incrementStep: $current->incrementStep,
            ),
            default => new FixedStepWeightConfig(
                targetImprovement: $current->targetImprovement,
                incrementStep: $current->incrementStep,
            ),
        };
    }

    protected function createRepConfig(string $strategy): PairedLadderRepConfig|ProportionalRepConfig
    {
        $current = $this->progressionConfig->repConfig;

        return match ($strategy) {
            'proportional' => new ProportionalRepConfig(
                startingReps: $current->startingReps,
                stepDownInterval: $current->stepDownInterval,
                minimumReps: $current->minimumReps,
            ),
            default => new PairedLadderRepConfig(
                startingReps: $current->startingReps,
                stepDownInterval: $current->stepDownInterval,
                repDecrement: $current instanceof PairedLadderRepConfig ? $current->repDecrement : 2,
                minimumReps: $current->minimumReps,
            ),
        };
    }

    protected function setOverride(array $params): void
    {
        $key = OverrideStore::buildSetKey(
            $params['exerciseId'],
            $params['weekIndex'],
            $params['sessionUuid'],
            $params['setIndex']
        );

        $existing = $this->overrideStore->getSetOverride($key);

        $this->overrideStore->setSetOverride($key, new SetOverride(
            reps: $params['reps'] ?? $existing?->reps,
            weight: $params['weight'] ?? $existing?->weight,
            locked: $existing?->locked ?? false,
            lockedAt: $existing?->lockedAt,
        ));

        $this->clearExerciseProgression($params['exerciseId']);
        $this->refreshGrid();
    }

    protected function clearSetOverride(array $params): void
    {
        $key = OverrideStore::buildSetKey(
            $params['exerciseId'],
            $params['weekIndex'],
            $params['sessionUuid'],
            $params['setIndex']
        );

        $this->overrideStore->removeSetOverride($key);
        $this->clearExerciseProgression($params['exerciseId']);
        $this->refreshGrid();
    }

    protected function setWeekAnchorOverride(array $params): void
    {
        $key = OverrideStore::buildWeekKey($params['exerciseId'], $params['weekIndex']);

        $this->overrideStore->setWeekAnchorOverride($key, new WeekAnchorOverride(
            value: $params['value'],
            scope: $params['scope'] ?? 'single',
            fromWeek: $params['weekIndex'],
            recalculateReps: $params['recalculateReps'] ?? false,
        ));

        $this->clearExerciseProgression($params['exerciseId']);
        $this->refreshGrid();
    }

    protected function clearWeekAnchorOverride(array $params): void
    {
        $key = OverrideStore::buildWeekKey($params['exerciseId'], $params['weekIndex']);
        $this->overrideStore->removeWeekAnchorOverride($key);
        $this->clearExerciseProgression($params['exerciseId']);
        $this->refreshGrid();
    }

    protected function updateProgressionConfig(array $params): void
    {
        foreach ($params as $key => $value) {
            if (property_exists($this->progressionConfig, $key)) {
                $this->progressionConfig->{$key} = $value;
            }
        }
        $this->clearExerciseProgressions();
        $this->refreshGrid();
    }

    protected function addSession(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        logger()->info('Adding session', ['weekIndex' => $params['weekIndex'], 'week' => $week?->uuid]);

        if ($week) {
            $this->tree->executeAction('session.add', [
                'parentId' => $week->uuid,
                'day' => $params['day'],
                'slot' => $params['slot'],
                'category' => $params['category'],
                'exercises' => $params['exercises'],
                'name' => $params['name'],
            ]);
            logger()->info('Session added', ['weekChildren' => count($week->getChildren())]);
            $this->refreshGrid();
        }
    }

    protected function updateSession(array $params): void
    {
        $this->tree->executeAction('session.update', [
            'sessionId' => $params['sessionId'],
            'category' => $params['category'],
            'exercises' => $params['exercises'],
            'name' => $params['name'],
        ]);
        $this->refreshGrid();
    }

    protected function deleteSession(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        if (! $week) {
            return;
        }

        $session = $this->getSessionAtPosition($week, $params['slot'], $params['day']);
        if ($session) {
            $this->tree->executeAction('session.delete', [
                'parentId' => $week->uuid,
                'sessionId' => $session->uuid,
            ]);
            $this->refreshGrid();
        }
    }

    protected function linkSession(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        if (! $week) {
            return;
        }

        $this->tree->executeAction('session.add', [
            'parentId' => $week->uuid,
            'day' => $params['day'],
            'slot' => $params['slot'],
            'linkId' => $params['linkedSessionUuid'],
        ]);

        $this->refreshGrid();
    }

    protected function updateSessionLink(array $params): void
    {
        $this->tree->executeAction('session.link', [
            'sessionId' => $params['sessionId'],
            'linkedTo' => $params['linkedTo'],
        ]);

        $this->refreshGrid();
    }

    protected function moveSession(array $params): void
    {
        $this->tree->executeAction('session.move', [
            'sessionId' => $params['sessionId'],
            'newDay' => $params['newDay'],
            'newSlot' => $params['newSlot'],
        ]);

        $this->refreshGrid();
    }

    protected function swapSessions(array $params): void
    {
        $this->tree->executeAction('session.swap', [
            'session1Id' => $params['session1Id'],
            'session2Id' => $params['session2Id'],
        ]);

        $this->refreshGrid();
    }

    protected function addWeek(array $params): void
    {
        $event = $this->tree->executeAction('week.add', [
            'parentId' => $this->tree->root->uuid,
        ]);

        $newWeek = $event->child ?? null;

        if ($newWeek && ! empty($params['linkedTo'])) {
            $this->tree->executeAction('week.link', [
                'weekId' => $newWeek->uuid,
                'linkedTo' => $params['linkedTo'],
            ]);
        }

        $this->syncProgressionBlockLength();
        $this->refreshGrid();
    }

    protected function linkWeek(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        if (! $week) {
            return;
        }

        $this->tree->executeAction('week.link', [
            'weekId' => $week->uuid,
            'linkedTo' => $params['linkedTo'],
        ]);

        $this->refreshGrid();
    }

    protected function deleteWeek(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        if (! $week || $params['weekIndex'] === 0) {
            return;
        }

        $this->tree->executeAction('week.delete', [
            'nodeId' => $week->uuid,
        ]);

        $this->syncProgressionBlockLength();
        $this->refreshGrid();
    }

    protected function getSessionAtPosition(TrainingNode $week, int $slot, int $day): ?TrainingNode
    {
        foreach ($week->getChildren() as $session) {
            if ($session->getData()->day === $day && $session->getData()->slot === $slot) {
                return $session;
            }
        }

        return null;
    }

    public function buildSessionMap(): array
    {
        if (! $this->tree) {
            return [];
        }

        $sessionMap = [];
        $sourceWeek = $this->tree->root->children[0] ?? null;

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

    public function getExerciseProgression(int $exerciseId): ?ExerciseProgression
    {
        if (isset($this->exerciseProgressions[$exerciseId])) {
            return $this->exerciseProgressions[$exerciseId];
        }

        if (! $this->tree) {
            return null;
        }

        $sessionMap = $this->buildSessionMap();
        $calculator = new ProgressionCalculator(
            $this->progressionConfig,
            $this->overrideStore,
            $this->athleteData,
        );

        $progression = $calculator->calculateExerciseProgression($exerciseId, $sessionMap);
        $this->exerciseProgressions[$exerciseId] = $progression;

        return $progression;
    }

    public function getExerciseProgressionData(int $exerciseId): ?array
    {
        $progression = $this->getExerciseProgression($exerciseId);

        if (! $progression) {
            return null;
        }

        return $progression->toArray();
    }

    protected function clearExerciseProgression(int $exerciseId): void
    {
        unset($this->exerciseProgressions[$exerciseId]);
    }

    protected function clearExerciseProgressions(): void
    {
        $this->exerciseProgressions = [];
    }

    public function setAthleteData(AthleteData $athleteData): void
    {
        $this->athleteData = $athleteData;
        $this->clearExerciseProgressions();
        $this->refreshGrid();
    }

    public function getStorageData(): array
    {
        return [
            'tree' => $this->tree?->root?->toArray(),
            'progressionConfig' => $this->progressionConfig->toArray(),
            'overrideStore' => $this->overrideStore->toArray(),
        ];
    }

    protected function refreshGrid(): void
    {
        $this->dispatch(
            'grid-refresh',
            block: $this->tree->root,
            progressionConfig: $this->progressionConfig->toArray(),
            overrideStore: $this->overrideStore->toArray(),
            athleteData: $this->athleteData->toArray(),
        );
    }

    public function render()
    {
        return view('livewire.block-creator');
    }
}
