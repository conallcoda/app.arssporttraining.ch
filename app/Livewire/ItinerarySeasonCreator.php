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

        $this->tree = TrainingTree::fromTrainingNode($seasonNode);
        $this->activeBlockIndex = 0;
    }

    #[Computed]
    public function activeBlock(): ?TrainingNode
    {
        return $this->tree->root->children[$this->activeBlockIndex] ?? null;
    }

    #[Computed]
    public function blocks(): array
    {
        return $this->tree->root->children;
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
            default => null,
        };
    }

    protected function addSession(array $params): void
    {
        $week = $this->activeBlock->children[$params['weekIndex']] ?? null;
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
            logger()->info('Session added', ['weekChildren' => count($week->children)]);
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
        $week = $this->activeBlock->children[$params['weekIndex']] ?? null;
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

    protected function getSessionAtPosition(TrainingNode $week, int $slot, int $day): ?TrainingNode
    {
        foreach ($week->children as $session) {
            if ($session->data->day === $day && $session->data->slot === $slot) {
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
