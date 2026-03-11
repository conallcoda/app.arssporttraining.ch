<?php

namespace App\Data\Athlete;

use App\Form\Fields\Athlete\DateOfBirth;
use App\Form\Fields\Athlete\Email;
use App\Form\Fields\Athlete\Forename;
use App\Form\Fields\Athlete\Gender;
use App\Form\Fields\Athlete\Phone;
use App\Form\Fields\Athlete\Surname;
use App\Models\Users\GenderEnum;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class AthleteData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $forename,
        public string $surname,
        public ?string $email = null,
        public ?string $phone = null,
        public ?int $gender = null,
        public ?string $dateOfBirth = null,
        public ?Carbon $updatedAt = null,
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
            gender: $user->gender?->value,
            dateOfBirth: $user->date_of_birth?->format('Y-m-d'),
            updatedAt: $user->updated_at,
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
                'gender' => $this->gender ? GenderEnum::from($this->gender) : null,
                'date_of_birth' => $this->dateOfBirth,
                'type' => UserTypeEnum::Athlete,
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
                Gender::make('gender'),
                DateOfBirth::make('dateOfBirth'),
            ]);
    }
}
