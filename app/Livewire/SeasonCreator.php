<?php

namespace App\Livewire;

use App\Models\Exercise\Exercise;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\ExerciseData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\SetData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SeasonCreator extends Component
{
    public ?int $numberOfBlocks = 2;
    public ?int $weeksPerBlock = 5;
    public ?int $sessionsPerWeek = 2;
    public ?int $weightPerExercise = 50;
    public string $defaultSets = '14-14-12-12';

    public ?int $activeCategory = null;
    public int $activeBlock = 1;

    public array $categoryExercises = [];

    public ?array $seasonTree = null;

    public array $flatData = [];
    public array $initialFlatData = [];

    public function mount()
    {
        foreach (TrainingSessionCategory::all() as $category) {
            $this->categoryExercises[$category->id] = [];
        }

        $this->activeCategory = 1;
        $this->categoryExercises[1][] = 3;

        $this->rebuildTree();
    }

    protected function rebuildTree(): void
    {
        $blocks = [];

        for ($blockIndex = 0; $blockIndex < ($this->numberOfBlocks ?? 0); $blockIndex++) {
            $weeks = [];

            for ($weekIndex = 0; $weekIndex < ($this->weeksPerBlock ?? 0); $weekIndex++) {
                $sessions = $this->createSessionsForWeek();
                $weeks[] = (new WeekData())->withChildren($sessions);
            }

            $blocks[] = (new BlockData())->withChildren($weeks);
        }

        $seasonData = (new SeasonData())->withChildren($blocks);
        $seasonNode = TrainingNode::fromData($seasonData);
        $seasonNode->name = 'New Season';

        $this->seasonTree = $seasonNode->toArray();

        $this->computeFlatData();

        $this->initialFlatData = $this->flatData;
    }

    protected function createSessionsForWeek(): array
    {
        $sessions = [];
        $sessionIndex = 0;

        for ($day = 0; $day < 7 && $sessionIndex < ($this->sessionsPerWeek ?? 0); $day++) {
            for ($slot = 0; $slot < 2 && $sessionIndex < ($this->sessionsPerWeek ?? 0); $slot++) {
                $exercises = $this->createExercisesForSession();

                $sessions[] = (new SessionData(
                    name: null,
                    day: $day,
                    slot: $slot,
                    category: $this->activeCategory,
                    exercises: []
                ))->withChildren($exercises);

                $sessionIndex++;
            }
        }

        return $sessions;
    }

    protected function createExercisesForSession(): array
    {
        $exercises = [];
        $exerciseIds = $this->categoryExercises[$this->activeCategory] ?? [];
        $sets = $this->parseSets();

        foreach ($exerciseIds as $exerciseId) {
            $setNodes = [];
            foreach ($sets as $reps) {
                $setNodes[] = new SetData(
                    reps: $reps,
                    weight: $this->weightPerExercise ?? 0
                );
            }

            $exercises[] = (new ExerciseData(
                exercise: $exerciseId
            ))->withChildren($setNodes);
        }

        return $exercises;
    }

    protected function computeFlatData(): void
    {
        $this->flatData = [];

        if (!$this->seasonTree) {
            return;
        }

        $seasonNode = TrainingNode::from($this->seasonTree);

        foreach ($seasonNode->children as $blockIndex => $block) {
            foreach ($block->children as $weekIndex => $week) {
                foreach ($week->children as $sessionIndex => $session) {
                    foreach ($session->children as $exerciseIndex => $exercise) {
                        $exerciseId = $exercise->data->exercise;
                        $categoryId = $session->data->category ?? $this->activeCategory;

                        $reps = [];
                        $weights = [];

                        foreach ($exercise->children as $set) {
                            $reps[] = $set->data->reps;
                            $weights[] = $set->data->weight;
                        }

                        $key = "{$categoryId}.{$exerciseId}." . ($blockIndex + 1) . "." . ($weekIndex + 1) . "." . ($sessionIndex + 1);

                        $this->flatData[$key] = [
                            'blockUuid' => $block->uuid,
                            'weekUuid' => $week->uuid,
                            'sessionUuid' => $session->uuid,
                            'exerciseUuid' => $exercise->uuid,
                            'reps' => $reps,
                            'weights' => $weights,
                        ];
                    }
                }
            }
        }
    }

    public function setActiveCategory(int $categoryId)
    {
        $this->activeCategory = $categoryId;
        $this->activeBlock = 1;
    }

    public function setActiveBlock(int $block)
    {
        $this->activeBlock = $block;
    }

    public function updatedNumberOfBlocks()
    {
        $this->rebuildTree();
    }

    public function updatedWeeksPerBlock()
    {
        $this->rebuildTree();
    }

    public function updatedSessionsPerWeek()
    {
        $this->rebuildTree();
    }

    public function updatedWeightPerExercise($value)
    {
        $weight = (float) ($value ?? 0);

        if (!$this->seasonTree) {
            return;
        }

        $seasonNode = TrainingNode::from($this->seasonTree);
        $this->updateAllSetWeights($seasonNode, $weight);
        $this->seasonTree = $seasonNode->toArray();
        $this->computeFlatData();
    }

    protected function updateAllSetWeights(TrainingNode $node, float $weight): void
    {
        if ($node->type === 'set') {
            $node->data->weight = $weight;
        }

        foreach ($node->children as $child) {
            $this->updateAllSetWeights($child, $weight);
        }
    }

    public function updatedDefaultSets($value)
    {
        $this->rebuildTree();
    }

    public function addExercise(int $exerciseId)
    {
        if (!$this->activeCategory || !$exerciseId) {
            return;
        }

        if (!in_array($exerciseId, $this->categoryExercises[$this->activeCategory])) {
            $this->categoryExercises[$this->activeCategory][] = $exerciseId;
            $this->addExerciseToTree($exerciseId);
        }
    }

    protected function addExerciseToTree(int $exerciseId): void
    {
        if (!$this->seasonTree) {
            return;
        }

        $seasonNode = TrainingNode::from($this->seasonTree);
        $sets = $this->parseSets();

        foreach ($seasonNode->children as $block) {
            foreach ($block->children as $week) {
                foreach ($week->children as $session) {
                    if ($session->data->category === $this->activeCategory) {
                        $setNodes = [];
                        foreach ($sets as $reps) {
                            $setNodes[] = new SetData(
                                reps: $reps,
                                weight: $this->weightPerExercise ?? 0
                            );
                        }

                        $exerciseData = (new ExerciseData(
                            exercise: $exerciseId
                        ))->withChildren($setNodes);

                        $exerciseNode = TrainingNode::fromData(
                            $exerciseData,
                            count($session->children),
                            $session->uuid,
                            $session->path ? array_merge($session->path, [$session->uuid]) : [$session->uuid]
                        );

                        $session->children[] = $exerciseNode;
                    }
                }
            }
        }

        $this->seasonTree = $seasonNode->toArray();
        $this->computeFlatData();
    }

    public function removeExercise(int $exerciseId)
    {
        if (!$this->activeCategory) {
            return;
        }

        $this->categoryExercises[$this->activeCategory] = array_values(
            array_filter(
                $this->categoryExercises[$this->activeCategory],
                fn($id) => $id !== $exerciseId
            )
        );

        $this->removeExerciseFromTree($exerciseId);
    }

    protected function removeExerciseFromTree(int $exerciseId): void
    {
        if (!$this->seasonTree) {
            return;
        }

        $seasonNode = TrainingNode::from($this->seasonTree);

        foreach ($seasonNode->children as $block) {
            foreach ($block->children as $week) {
                foreach ($week->children as $session) {
                    $session->children = array_values(array_filter(
                        $session->children,
                        fn($exercise) => $exercise->data->exercise !== $exerciseId
                    ));

                    foreach ($session->children as $index => $exercise) {
                        $exercise->sequence = $index;
                    }
                }
            }
        }

        $this->seasonTree = $seasonNode->toArray();
        $this->computeFlatData();
    }

    public function parseSets(): array
    {
        return array_map('intval', explode('-', $this->defaultSets));
    }

    public function getExerciseData(int $exerciseId, int $block, int $week, int $session): array
    {
        $key = "{$this->activeCategory}.{$exerciseId}.{$block}.{$week}.{$session}";
        $sets = $this->parseSets();

        return $this->flatData[$key] ?? [
            'reps' => $sets,
            'weights' => array_fill(0, count($sets), $this->weightPerExercise ?? 0),
        ];
    }

    public function updateCell(int $exerciseId, int $block, int $week, int $session, string $type, int $setIndex, $value)
    {
        $key = "{$this->activeCategory}.{$exerciseId}.{$block}.{$week}.{$session}";

        if (!isset($this->flatData[$key])) {
            return;
        }

        $data = $this->flatData[$key];
        $exerciseUuid = $data['exerciseUuid'];

        if (!$this->seasonTree) {
            return;
        }

        $seasonNode = TrainingNode::from($this->seasonTree);
        $exerciseNode = $this->findNodeByUuid($seasonNode, $exerciseUuid);

        if ($exerciseNode && isset($exerciseNode->children[$setIndex])) {
            $setNode = $exerciseNode->children[$setIndex];
            if ($type === 'reps') {
                $setNode->data->reps = (int) $value;
            } else {
                $setNode->data->weight = (float) $value;
            }
        }

        $this->seasonTree = $seasonNode->toArray();
        $this->computeFlatData();
    }

    protected function findNodeByUuid(TrainingNode $node, string $uuid): ?TrainingNode
    {
        if ($node->uuid === $uuid) {
            return $node;
        }

        foreach ($node->children as $child) {
            $found = $this->findNodeByUuid($child, $uuid);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    #[Computed]
    public function categories()
    {
        return TrainingSessionCategory::all();
    }

    #[Computed]
    public function exercises()
    {
        return Exercise::orderBy('name')->get();
    }

    #[Computed]
    public function selectedExercises()
    {
        if (!$this->activeCategory || empty($this->categoryExercises[$this->activeCategory])) {
            return collect();
        }

        return Exercise::whereIn('id', $this->categoryExercises[$this->activeCategory])->get();
    }

    #[Computed]
    public function setCount(): int
    {
        return count($this->parseSets());
    }

    public function getInitialData(int $exerciseId): array
    {
        $rows = [];

        for ($week = 1; $week <= ($this->weeksPerBlock ?? 0); $week++) {
            for ($session = 1; $session <= ($this->sessionsPerWeek ?? 0); $session++) {
                $key = "{$this->activeCategory}.{$exerciseId}.{$this->activeBlock}.{$week}.{$session}";
                $data = $this->initialFlatData[$key] ?? null;

                if ($data) {
                    $rows[] = [
                        'week' => $week,
                        'session' => $session,
                        'reps' => $data['reps'],
                        'weights' => $data['weights'],
                    ];
                } else {
                    $sets = $this->parseSets();
                    $rows[] = [
                        'week' => $week,
                        'session' => $session,
                        'reps' => $sets,
                        'weights' => array_fill(0, count($sets), $this->weightPerExercise ?? 0),
                    ];
                }
            }
        }

        return $rows;
    }

    public function getCurrentDataRows(int $exerciseId): array
    {
        $rows = [];

        for ($week = 1; $week <= ($this->weeksPerBlock ?? 0); $week++) {
            for ($session = 1; $session <= ($this->sessionsPerWeek ?? 0); $session++) {
                $data = $this->getExerciseData($exerciseId, $this->activeBlock, $week, $session);
                $rows[] = [
                    'week' => $week,
                    'session' => $session,
                    'reps' => $data['reps'],
                    'weights' => $data['weights'],
                ];
            }
        }

        return $rows;
    }

    public function render()
    {
        return view('livewire.season-creator');
    }
}
