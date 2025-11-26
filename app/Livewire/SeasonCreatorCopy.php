<?php

namespace App\Livewire;

use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingSessionCategory;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SeasonCreatorCopy extends Component
{
    public ?int $numberOfBlocks = 2;
    public ?int $weeksPerBlock = 5;
    public ?int $sessionsPerWeek = 2;
    public ?int $weightPerExercise = 50;
    public string $defaultSets = '14-14-12-12';

    public ?int $activeCategory = null;
    public int $activeBlock = 1;

    public array $categoryExercises = [];
    public array $exerciseData = [];
    public array $initialData = [];
    public array $lastConfigHash = [];

    public function mount()
    {
        foreach (TrainingSessionCategory::all() as $category) {
            $this->categoryExercises[$category->id] = [];
        }

        $this->activeCategory = 1;
        $this->categoryExercises[1][] = 3;
        $this->initializeExerciseDataForCategory(1, 3);
    }

    protected function initializeExerciseDataForCategory(int $categoryId, int $exerciseId)
    {
        $sets = $this->parseSets();
        $weights = array_fill(0, count($sets), $this->weightPerExercise ?? 0);

        for ($block = 1; $block <= ($this->numberOfBlocks ?? 0); $block++) {
            for ($week = 1; $week <= ($this->weeksPerBlock ?? 0); $week++) {
                for ($session = 1; $session <= ($this->sessionsPerWeek ?? 0); $session++) {
                    $key = "{$categoryId}.{$exerciseId}.{$block}.{$week}.{$session}";
                    $this->exerciseData[$key] = [
                        'reps' => $sets,
                        'weights' => $weights,
                    ];
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

    public function updatedWeightPerExercise($value)
    {
        $weight = $value ?? 0;
        foreach ($this->exerciseData as $key => $data) {
            $this->exerciseData[$key]['weights'] = array_fill(0, count($data['weights']), $weight);
        }
    }

    public function updatedDefaultSets($value)
    {
        $sets = $this->parseSets();
        $weight = $this->weightPerExercise ?? 0;
        foreach ($this->exerciseData as $key => $data) {
            $this->exerciseData[$key]['reps'] = $sets;
            $this->exerciseData[$key]['weights'] = array_fill(0, count($sets), $weight);
        }
    }

    public function addExercise(int $exerciseId)
    {
        if (!$this->activeCategory || !$exerciseId) {
            return;
        }

        if (!in_array($exerciseId, $this->categoryExercises[$this->activeCategory])) {
            $this->categoryExercises[$this->activeCategory][] = $exerciseId;
            $this->initializeExerciseData($exerciseId);
        }
    }

    protected function initializeExerciseData(int $exerciseId)
    {
        $sets = $this->parseSets();
        $weights = array_fill(0, count($sets), $this->weightPerExercise ?? 0);

        for ($block = 1; $block <= ($this->numberOfBlocks ?? 0); $block++) {
            for ($week = 1; $week <= ($this->weeksPerBlock ?? 0); $week++) {
                for ($session = 1; $session <= ($this->sessionsPerWeek ?? 0); $session++) {
                    $key = "{$this->activeCategory}.{$exerciseId}.{$block}.{$week}.{$session}";
                    $this->exerciseData[$key] = [
                        'reps' => $sets,
                        'weights' => $weights,
                    ];
                }
            }
        }
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

        foreach (array_keys($this->exerciseData) as $key) {
            if (str_starts_with($key, "{$this->activeCategory}.{$exerciseId}.")) {
                unset($this->exerciseData[$key]);
            }
        }
    }

    public function parseSets(): array
    {
        return array_map('intval', explode('-', $this->defaultSets));
    }

    public function getExerciseData(int $exerciseId, int $block, int $week, int $session): array
    {
        $key = "{$this->activeCategory}.{$exerciseId}.{$block}.{$week}.{$session}";
        $sets = $this->parseSets();
        return $this->exerciseData[$key] ?? [
            'reps' => $sets,
            'weights' => array_fill(0, count($sets), $this->weightPerExercise ?? 0),
        ];
    }

    public function updateCell(int $exerciseId, int $block, int $week, int $session, string $type, int $setIndex, int $value)
    {
        $key = "{$this->activeCategory}.{$exerciseId}.{$block}.{$week}.{$session}";
        $sets = $this->parseSets();
        if (!isset($this->exerciseData[$key])) {
            $this->exerciseData[$key] = [
                'reps' => $sets,
                'weights' => array_fill(0, count($sets), $this->weightPerExercise ?? 0),
            ];
        }
        $this->exerciseData[$key][$type][$setIndex] = $value;
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

    public function getSpreadsheetConfig(int $exerciseId): array
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

        $configKey = "{$this->activeCategory}.{$exerciseId}.{$this->activeBlock}";
        $configHash = md5("{$this->weeksPerBlock}-{$this->sessionsPerWeek}-{$this->defaultSets}-{$this->weightPerExercise}");

        if (!isset($this->lastConfigHash[$configKey]) || $this->lastConfigHash[$configKey] !== $configHash) {
            $this->initialData[$configKey] = $rows;
            $this->lastConfigHash[$configKey] = $configHash;
        }

        return [
            'exerciseId' => $exerciseId,
            'block' => $this->activeBlock,
            'setCount' => $this->setCount(),
            'rows' => $rows,
        ];
    }

    public function getInitialData(int $exerciseId): array
    {
        $configKey = "{$this->activeCategory}.{$exerciseId}.{$this->activeBlock}";
        $configHash = md5("{$this->weeksPerBlock}-{$this->sessionsPerWeek}-{$this->defaultSets}-{$this->weightPerExercise}");

        if (!isset($this->lastConfigHash[$configKey]) || $this->lastConfigHash[$configKey] !== $configHash) {
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
            $this->initialData[$configKey] = $rows;
            $this->lastConfigHash[$configKey] = $configHash;
        }

        return $this->initialData[$configKey] ?? [];
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
