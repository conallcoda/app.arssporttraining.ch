<?php

namespace App\Data\Coach;

use App\Form\Fields\Coach\Color;
use App\Form\Fields\Coach\Email;
use App\Form\Fields\Coach\Forename;
use App\Form\Fields\Coach\Phone;
use App\Form\Fields\Coach\Surname;
use App\Models\Users\AccountSetupStatus;
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
        public string $setupStatus = '',
        public string $setupStatusLabel = '',
        public string $setupStatusColor = 'zinc',
    ) {}

    public function name(): string
    {
        return trim("{$this->forename} {$this->surname}");
    }

    public static function fromModel(User $user): self
    {
        $setupStatus = $user->accountSetupStatus();

        return new self(
            id: $user->id,
            forename: $user->forename ?? '',
            surname: $user->surname ?? '',
            email: $user->email,
            phone: $user->phone,
            color: $user->color,
            updatedAt: $user->updated_at,
            personName: trim(($user->surname ?? '').', '.($user->forename ?? ''), ', '),
            setupStatus: $setupStatus->value,
            setupStatusLabel: $setupStatus->label(),
            setupStatusColor: $setupStatus->color(),
        );
    }

    public function persist(): void
    {
        $existingUser = $this->id ? User::find($this->id) : null;

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

        if ($existingUser !== null) {
            $user->invalidatePendingAccountSetupIfEmailChanged($existingUser->email, $this->email);
        }
    }

    /** @return list<array{label: string, color: string}> */
    public function getSetupStatusBadge(): array
    {
        return [[
            'label' => $this->setupStatusLabel,
            'color' => $this->setupStatusColor,
        ]];
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
