<?php

namespace App\Livewire\Database;

use App\Data\Coach\CoachData;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\PersonName;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Form\Forms\ChangePasswordForm;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\ColorPalette;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class CoachList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'cl_';
    }

    protected function getDataClass(): string
    {
        return CoachData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return User::query()->where('type', UserTypeEnum::Coach);
    }

    protected function getExtraActions(): array
    {
        return [
            ...parent::getExtraActions(),
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

    public function handleChangePasswordSubmitted(array $data): void
    {
        $user = User::findOrFail($data['id']);
        $user->update(['password' => Hash::make($data['password'])]);

        Flux::toast(text: "Password changed for {$user->name}", variant: 'success');
    }

    public function handleFormSubmitted(array $data): void
    {
        $data['name'] = trim(($data['forename'] ?? '').' '.($data['surname'] ?? ''));

        parent::handleFormSubmitted($data);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                PersonName::make('personName')
                    ->label(__('Name'))
                    ->width('w-2/3')
                    ->modal(),
                ColorBadge::make('color')
                    ->label(__('Color'))
                    ->colorLabels(ColorPalette::COLORS),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->defaultSort('personName', 'asc')
            ->sortable(['id', 'personName', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $q) use ($value): void {
                        $q->where('forename', 'like', '%'.$value.'%')
                            ->orWhere('surname', 'like', '%'.$value.'%');
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search coaches...'))
                    ),
            ]);
    }
}
