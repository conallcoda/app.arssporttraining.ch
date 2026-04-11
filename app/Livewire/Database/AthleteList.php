<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteData;
use App\Form\Fields\CoachFilter;
use App\Livewire\Concerns\ClearsCoachFilterOnTabSwitch;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\PersonName;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\OwnershipTabs;
use Coda\FormKit\Action;
use Coda\FormKit\Fields\Pillbox;
use Coda\FormKit\Fields\Text as TextField;
use Coda\FormKit\Forms\ChangePasswordForm;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class AthleteList extends AbstractModelList
{
    use ClearsCoachFilterOnTabSwitch;

    protected function urlPrefix(): string
    {
        return 'al_';
    }

    protected function getDataClass(): string
    {
        return AthleteData::class;
    }

    protected function getTabs(): array
    {
        return OwnershipTabs::make('Athletes')->toArray();
    }

    protected function getDefaultTabKey(): ?string
    {
        return OwnershipTabs::make('Athletes')->defaultTab($this->getBaseQuery());
    }

    protected function getBaseQuery(): Builder
    {
        return User::query()->where('type', UserTypeEnum::Athlete)->with([
            'internalTags',
            'owner',
            'metricSubmissions' => function ($query): void {
                $query->whereRaw('user_metric_submissions.id = (
                    SELECT ms2.id FROM user_metric_submissions ms2
                    WHERE ms2.user_id = user_metric_submissions.user_id
                    AND ms2.metric = user_metric_submissions.metric
                    AND ms2.deleted_at IS NULL
                    AND ms2.recorded_at <= CURDATE()
                    ORDER BY ms2.recorded_at DESC, ms2.created_at DESC
                    LIMIT 1
                )')->with('values');
            },
        ]);
    }

    protected function getExtraActions(): array
    {
        return [
            ...parent::getExtraActions(),
            Action::make('metrics', __('Metrics'))
                ->row()
                ->icon('activity')
                ->handler('openMetrics'),
            Action::make('changePassword', __('Change Password'))
                ->rowMenu()
                ->icon('lock')
                ->formModal(ChangePasswordForm::class, __('Change Password'))
                ->prepareData(fn (User $model) => [
                    'id' => $model->id,
                    '_name' => $model->name,
                ])
                ->handler('handleChangePasswordSubmitted'),
        ];
    }

    public function openMetrics(int $id): void
    {
        $this->redirect(route('athlete-metric-index', ['athleteId' => $id]));
    }

    public function handleChangePasswordSubmitted(array $data): void
    {
        $user = User::findOrFail($data['id']);
        $user->update(['password' => Hash::make($data['password'])]);

        Flux::toast(text: "Password changed for {$user->name}", variant: 'success');
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->forScope('athlete_internal')
            ->pluck('name', 'id');

        return Table::make()
            ->columns([
                Id::make(),
                PersonName::make('personName')
                    ->label(__('Name'))
                    ->width('w-1/3')
                    ->modal(),
                Badge::make('metrics')
                    ->label(__('Metrics'))
                    ->source(fn (AthleteData $data) => $data->getMetricBadges()),
                Badge::make('coach')
                    ->label(__('Coach'))
                    ->source(fn (AthleteData $data) => [
                        [
                            'label' => $data->ownerName ?? __('Unassigned'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                Badge::make('internalTags')
                    ->label(__('Tags'))
                    ->source(fn (AthleteData $data) => collect($data->internalTags)
                        ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => 'internalTags'])
                        ->all()
                    ),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->defaultSort('personName', 'asc')
            ->sortable(['id', 'personName', 'coach', 'updatedAt'])
            ->filters($this->buildFilters());
    }

    private function buildFilters(): array
    {
        $filters = [
            TableFilter::callback('search', function (Builder $query, mixed $value): void {
                $query->where(function (Builder $q) use ($value): void {
                    $q->where('forename', 'like', '%'.$value.'%')
                        ->orWhere('surname', 'like', '%'.$value.'%');
                });
            })
                ->field(
                    TextField::make('search')
                        ->label(__('Search'))
                        ->placeholder(__('Search athletes...'))
                ),
            TableFilter::callback('tags', function (Builder $query, mixed $value): void {
                $query->whereHas('internalTags', fn (Builder $q) => $q->whereIn('tags.id', (array) $value));
            })
                ->field(
                    (new Pillbox('tags'))
                        ->label(__('Tags'))
                        ->placeholder(__('Filter by tags...'))
                        ->options(Tag::query()->forScope('athlete_internal')->pluck('name', 'id')->all())
                ),
        ];

        if ($this->selectedTab === 'all') {
            $filters[] = TableFilter::callback('coach', function (Builder $query, mixed $value): void {
                $query->whereIn('users.owner_id', (array) $value);
            })
                ->field(new CoachFilter('coach'));
        }

        return $filters;
    }
}
