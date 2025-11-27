<?php

namespace App\Livewire;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ItinerarySeasonCreator extends Component
{
    public ItineraryConfig $config;

    public TrainingTree $tree;

    public int $activeBlockIndex = 0;

    public function mount()
    {
        $this->config = new ItineraryConfig();
        $this->rebuildTree();
    }

    protected function rebuildTree(): void
    {
        $blocks = [];
        $firstWeekNode = null;

        for ($blockIndex = 0; $blockIndex < $this->config->numberOfBlocks; $blockIndex++) {
            $weeks = [];

            for ($weekIndex = 0; $weekIndex < $this->config->weeksPerBlock; $weekIndex++) {
                $weeks[] = new WeekData();
            }

            $blocks[] = (new BlockData())->withChildren($weeks);
        }

        $seasonData = (new SeasonData())->withChildren($blocks);
        $seasonNode = TrainingNode::fromData($seasonData);
        $seasonNode->name = 'New Season';

        $firstWeekNode = $seasonNode->children[0]->children[0] ?? null;

        if ($firstWeekNode) {
            foreach ($seasonNode->children as $blockIndex => $block) {
                foreach ($block->children as $weekIndex => $_) {
                    if ($blockIndex === 0 && $weekIndex === 0) {
                        continue;
                    }
                    $block->children[$weekIndex] = $firstWeekNode->createLinkedClone($block->uuid, $weekIndex);
                }
            }
        }

        $this->tree = TrainingTree::fromTrainingNode($seasonNode);
        $this->activeBlockIndex = 0;

        $this->addDefaultSession();
        $this->dispatch('grid-refresh', block: $this->activeBlock);
    }

    protected function addDefaultSession(): void
    {
        $firstBlock = $this->tree->root->children[0] ?? null;
        if (!$firstBlock) {
            return;
        }

        $firstWeek = $firstBlock->children[0] ?? null;

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
    public function activeBlock(): ?TrainingNode
    {
        return $this->tree->root->getChildren()[$this->activeBlockIndex] ?? null;
    }

    #[Computed]
    public function blocks(): array
    {
        return $this->tree->root->getChildren();
    }

    #[Computed]
    public function nonLinkedSessions(): array
    {
        $sessions = [];
        $firstWeek = $this->tree->root->children[0]->children[0] ?? null;

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

    public function setActiveBlock(int $index): void
    {
        $this->activeBlockIndex = $index;
    }

    #[On('itinerary-config-changed')]
    public function onSeasonConfigChanged(array $config)
    {
        $this->config = ItineraryConfig::from($config);
        $this->rebuildTree();
    }

    #[On('itinerary-action')]
    public function onItineraryAction(string $action, array $params)
    {
        logger()->info('Received itinerary-action', ['action' => $action, 'params' => $params]);

        match ($action) {
            'session.add' => $this->addSession($params),
            'session.update' => $this->updateSession($params),
            'session.delete' => $this->deleteSession($params),
            'session.link' => $this->linkSession($params),
            'session.move' => $this->moveSession($params),
            'session.swap' => $this->swapSessions($params),
            default => null,
        };
    }

    protected function addSession(array $params): void
    {
        $week = $this->activeBlock->getChildren()[$params['weekIndex']] ?? null;
        logger()->info('Adding session', ['weekIndex' => $params['weekIndex'], 'week' => $week?->uuid, 'activeBlock' => $this->activeBlock?->uuid]);

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
            $this->dispatch('grid-refresh', block: $this->activeBlock);
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
        $this->dispatch('grid-refresh', block: $this->activeBlock);
    }

    protected function deleteSession(array $params): void
    {
        $week = $this->activeBlock->getChildren()[$params['weekIndex']] ?? null;
        if (!$week) {
            return;
        }

        $session = $this->getSessionAtPosition($week, $params['slot'], $params['day']);
        if ($session) {
            $this->tree->executeAction('session.delete', [
                'parentId' => $week->uuid,
                'sessionId' => $session->uuid,
            ]);
            $this->dispatch('grid-refresh', block: $this->activeBlock);
        }
    }

    protected function linkSession(array $params): void
    {
        $week = $this->activeBlock->getChildren()[$params['weekIndex']] ?? null;
        if (!$week) {
            return;
        }

        $this->tree->executeAction('session.add', [
            'parentId' => $week->uuid,
            'day' => $params['day'],
            'slot' => $params['slot'],
            'linkId' => $params['linkedSessionUuid'],
        ]);

        $this->dispatch('grid-refresh', block: $this->activeBlock);
    }

    protected function moveSession(array $params): void
    {
        $this->tree->executeAction('session.move', [
            'sessionId' => $params['sessionId'],
            'newDay' => $params['newDay'],
            'newSlot' => $params['newSlot'],
        ]);

        $this->dispatch('grid-refresh', block: $this->activeBlock);
    }

    protected function swapSessions(array $params): void
    {
        $this->tree->executeAction('session.swap', [
            'session1Id' => $params['session1Id'],
            'session2Id' => $params['session2Id'],
        ]);

        $this->dispatch('grid-refresh', block: $this->activeBlock);
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

    public function render()
    {
        return view('livewire.itinerary-season-creator');
    }
}
