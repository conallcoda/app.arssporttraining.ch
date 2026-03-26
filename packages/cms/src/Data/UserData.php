<?php

namespace Coda\Cms\Data;

use Carbon\Carbon;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;
use Coda\Cms\Models\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Model;

class UserData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $forename,
        public string $surname = '',
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $type = null,
        public ?Carbon $updatedAt = null,
        public string $personName = '',
    ) {}

    public static function fromModel(Model $user): self
    {
        return new self(
            id: $user->id,
            forename: $user->forename ?? '',
            surname: $user->surname ?? '',
            email: $user->email,
            phone: $user->phone,
            type: $user->type?->value,
            updatedAt: $user->updated_at,
            personName: trim(($user->surname ?? '').', '.($user->forename ?? ''), ', '),
        );
    }

    public function persist(): void
    {
        $userModel = config('cms.models.user');

        $user = $userModel::updateOrCreate(
            ['id' => $this->id],
            [
                'forename' => $this->forename,
                'surname' => $this->surname,
                'email' => $this->email,
                'phone' => $this->phone,
                'type' => $this->type ?? UserTypeEnum::User,
            ]
        );

        $this->id = $user->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('forename')->label('Forename')->required(),
                Text::make('surname')->label('Surname'),
                Text::make('email')->label('Email')->rules('nullable|email'),
                Text::make('phone')->label('Phone'),
            ]);
    }
}
