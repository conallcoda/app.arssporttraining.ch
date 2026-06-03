<?php

namespace App\Livewire\Database;

use App\Data\Coach\CoachData;
use App\Models\Users\AccountSetupStatus;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use App\Notifications\AccountSetupNotification;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\PersonName;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\ColorPalette;
use Coda\FormKit\Action;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text as TextField;
use Coda\Cms\Form\Forms\ChangePasswordForm;
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
            Action::make('sendSetupAccountEmail', __('Send Setup Email'))
                ->rowMenu()
                ->icon('at-symbol')
                ->handler('sendSetupAccountEmail'),
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

    public function sendSetupAccountEmail(int $id): void
    {
        $user = User::query()
            ->where('type', UserTypeEnum::Coach)
            ->findOrFail($id);

        if (! $user->hasSetupEmail()) {
            Flux::toast(text: "Add an email address for {$user->name} before sending a setup email.", variant: 'danger');

            return;
        }

        $token = $user->issueAccountSetupToken();
        $user->notify(new AccountSetupNotification($token));

        Flux::toast(text: "Setup email sent to {$user->email}", variant: 'success');
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
                    ->width('w-1/2')
                    ->modal(),
                Badge::make('setupStatus')
                    ->label(__('Setup Status'))
                    ->source(fn (CoachData $data) => $data->getSetupStatusBadge()),
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
                TableFilter::callback('setup_status', function (Builder $query, mixed $value): void {
                    $query->forAccountSetupStatus((string) $value);
                })
                    ->field(
                        Select::make('setup_status')
                            ->label(__('Setup Status'))
                            ->placeholder(__('All setup statuses'))
                            ->options(AccountSetupStatus::options())
                    ),
            ]);
    }
}
