<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Form\TableColumn;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\ExerciseType;
use App\Models\Exercise\Exercise;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ExerciseList extends AbstractModelList
{
    #[Url(as: 'type')]
    public string $typeFilter = 'all';

    protected function getDataClass(): string
    {
        return ExerciseData::class;
    }

    protected function getBaseQuery(): Builder
    {
        $query = Exercise::query();

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        return $query;
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseData::from($formData);
    }

    #[Computed]
    public function typeCounts(): array
    {
        $counts = Exercise::query()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->all();

        $total = array_sum($counts);

        return ['all' => $total] + $counts;
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.database.exercise-list', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'compact' => $this->compact,
            'sortable' => $this->isSortable(),
            'exerciseTypes' => ExerciseType::cases(),
        ]);
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::id(),
            TableColumn::text('name')
                ->label('Name')
                ->width('w-1/3')
                ->modal(),
            TableColumn::text('type')
                ->label('Type')
                ->width('w-1/6')
                ->enum(ExerciseType::class)
                ->modal()
                ->badge(),
            TableColumn::text('defaults')
                ->label('Defaults')
                ->badge()
                ->source(fn (ExerciseData $data) => $data->getDefaultsBadges()),
        ];
    }
}
