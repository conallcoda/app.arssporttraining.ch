<?php

namespace App\Data\Athlete;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Athlete\Forename;
use App\Form\Fields\Athlete\Surname;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;

class AthleteData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $forename,
        public string $surname,
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
            surname: $user->surname ?? ''
        );
    }

    public function persist(): void
    {
        $user = User::updateOrCreate(
            ['id' => $this->id],
            [
                'forename' => $this->forename,
                'surname' => $this->surname,
                'type' => UserTypeEnum::Athlete,
                'config' => [],
            ]
        );

        $this->id = $user->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                Forename::make('forename'),
                Surname::make('surname'),
            ])
            ->fieldset('general', 'General', ['forename', 'surname']);
    }
}
