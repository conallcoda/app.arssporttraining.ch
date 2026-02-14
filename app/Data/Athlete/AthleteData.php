<?php

namespace App\Data\Athlete;

use App\Form\Fields\Athlete\Forename;
use App\Form\Fields\Athlete\Surname;
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
            ]);
    }
}
