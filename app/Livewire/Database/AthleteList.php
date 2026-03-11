<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteData;
use App\Form\AdminChangePasswordForm;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class AthleteList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'al_';
    }

    protected function getDataClass(): string
    {
        return AthleteData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return User::query()->where('type', UserTypeEnum::Athlete);
    }

    protected function getExtraActions(): array
    {
        return [
            ...parent::getExtraActions(),
            Action::make('changePassword', __('Change Password'))
                ->rowMenu()
                ->icon('lock')
                ->formModal(AdminChangePasswordForm::class, __('Change Password'))
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

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('forename')
                    ->label(__('Forename'))
                    ->width('w-1/3')
                    ->modal(),
                Text::make('surname')
                    ->label(__('Surname'))
                    ->width('w-1/3')
                    ->modal(),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'forename', 'surname', 'updatedAt'])
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
                            ->placeholder(__('Search athletes...'))
                    ),
            ]);
    }
}
