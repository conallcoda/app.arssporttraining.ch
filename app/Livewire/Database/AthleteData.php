<?php

namespace App\Livewire\Database;

use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Models\Contracts\HasForms;
use App\Data\AbstractData;
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

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            forename: $user->forename ?? '',
            surname: $user->surname ?? ''
        );
    }

    public function persist(): void
    {
        $configData = [];
        if ($this->id === null) {
            $user = User::create([
                'forename' => $this->forename,
                'surname' => $this->surname,
                'type' => UserTypeEnum::Athlete,
                'config' => $configData,
            ]);
            $this->id = $user->id;
        } else {
            $user = User::findOrFail($this->id);
            $user->forename = $this->forename;
            $user->surname = $this->surname;
            $user->config = $configData;
            $user->save();
        }
    }

    public static function getForm(): array
    {
        return [
            Forename::make('forename'),
            Surname::make('surname'),
        ];
    }
}
