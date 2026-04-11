<?php

namespace App\Data\Coach;

use App\Form\Fields\Coach\Color;
use App\Form\Fields\Coach\Email;
use App\Form\Fields\Coach\Forename;
use App\Form\Fields\Coach\Phone;
use App\Form\Fields\Coach\Surname;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;

class CoachData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $forename,
        public string $surname,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $color = null,
        public ?Carbon $updatedAt = null,
        public string $personName = '',
    ) {}

    public function name(): string
    {
        return trim("{$this->forename} {$this->surname}");
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            forename: $user->forename ?? '',
            surname: $user->surname ?? '',
            email: $user->email,
            phone: $user->phone,
            color: $user->color,
            updatedAt: $user->updated_at,
            personName: trim(($user->surname ?? '').', '.($user->forename ?? ''), ', '),
        );
    }

    public function persist(): void
    {
        $user = User::updateOrCreate(
            ['id' => $this->id],
            [
                'forename' => $this->forename,
                'surname' => $this->surname,
                'email' => $this->email,
                'phone' => $this->phone,
                'color' => $this->color,
                'type' => UserTypeEnum::Coach,
                'config' => [],
            ]
        );

        $this->id = $user->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Forename::make('forename'),
                Surname::make('surname'),
                Email::make('email'),
                Phone::make('phone'),
                Color::make('color'),
            ]);
    }
}
