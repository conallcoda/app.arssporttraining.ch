<?php

namespace App\Livewire;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\ProgressionRules\Anchored\AnchoredProgression;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class BlockCreator extends Component
{
    public ?TrainingTree $tree = null;

    protected int $defaultWeeks = 5;

    protected string $storageKey = 'block-creator-tree';

    public function loadFromStorage(array $treeData)
    {
        TrainingNode::clearRegistry();
        $root = TrainingNode::from($treeData);
        $this->tree = TrainingTree::fromTrainingNode($root);
        $this->refreshGrid();
    }

    public function initializeWithDefaults()
    {
        $this->initializeTree();
    }

    public function clearStorage()
    {
        $this->initializeTree();
    }

    protected function initializeTree(): void
    {
        $weeks = [];

        for ($weekIndex = 0; $weekIndex < $this->defaultWeeks; $weekIndex++) {
            $weeks[] = new WeekData();
        }

        $blockData = (new BlockData())->withChildren($weeks);
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

        if ($firstWeek) {
            $event = $this->tree->executeAction('session.add', [
                'parentId' => $firstWeek->uuid,
                'day' => 0,
                'slot' => 0,
                'category' => 1,
                'exercises' => [1, 2, 3],
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
            'session.updateProgressionOverride' => $this->updateProgressionOverride($params),
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
            'set.update' => $this->updateSet($params),
            default => null,
        };
    }

    protected function updateSet(array $params): void
    {
        $this->tree->executeAction('set.update', [
            'setId' => $params['setId'],
            'reps' => (int) $params['reps'],
            'weight' => (float) $params['weight'],
        ]);
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
        if (!$week) {
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
        if (!$week) {
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

        if ($newWeek && !empty($params['linkedTo'])) {
            $this->tree->executeAction('week.link', [
                'weekId' => $newWeek->uuid,
                'linkedTo' => $params['linkedTo'],
            ]);
        }

        $this->refreshGrid();
    }

    protected function linkWeek(array $params): void
    {
        $week = $this->tree->root->getChildren()[$params['weekIndex']] ?? null;
        if (!$week) {
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
        if (!$week || $params['weekIndex'] === 0) {
            return;
        }

        $this->tree->executeAction('week.delete', [
            'nodeId' => $week->uuid,
        ]);

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

    protected function updateProgressionOverride(array $params): void
    {
        $weekUuid = $params['weekUuid'];
        $sessionDay = (int) $params['sessionDay'];
        $sessionSlot = (int) $params['sessionSlot'];
        $exerciseId = (int) $params['exerciseId'];
        $setIndex = (int) $params['setIndex'];
        $field = $params['field'];
        $value = $params['value'];

        $week = null;
        foreach ($this->tree->root->getChildren() as $w) {
            if ($w->uuid === $weekUuid) {
                $week = $w;
                break;
            }
        }

        if (!$week) {
            return;
        }

        $sessionKey = $sessionDay . '-' . $sessionSlot;

        $data = $week->getData();
        $overrides = $data->progressionOverrides;

        if (!isset($overrides[$sessionKey])) {
            $overrides[$sessionKey] = [];
        }
        if (!isset($overrides[$sessionKey][$exerciseId])) {
            $overrides[$sessionKey][$exerciseId] = [];
        }
        if (!isset($overrides[$sessionKey][$exerciseId][$setIndex])) {
            $overrides[$sessionKey][$exerciseId][$setIndex] = [];
        }

        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            $overrides[$sessionKey][$exerciseId][$setIndex]['reps'] = null;
            $overrides[$sessionKey][$exerciseId][$setIndex]['weight'] = null;
        } else {
            $overrides[$sessionKey][$exerciseId][$setIndex][$field] = $field === 'reps' ? (int) $value : (float) $value;
        }

        $data->progressionOverrides = $overrides;

        $this->refreshGrid();
    }

    protected function refreshGrid(): void
    {
        $this->applyProgressions();
        $this->dispatch('grid-refresh', block: $this->tree->root);
    }

    protected function applyProgressions(): void
    {
        $progression = new AnchoredProgression(start: 50, increase: 7.5, increaseStep: 0.5);
        $progression->apply($this->tree);
    }

    public function render()
    {
        return view('livewire.block-creator');
    }
}
