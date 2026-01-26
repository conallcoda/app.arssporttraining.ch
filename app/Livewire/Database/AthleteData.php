<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;

class AthleteData extends AbstractData implements HasForms
{
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
        $extra = [];
        if ($this->id === null) {
            $user = User::create([
                'forename' => $this->forename,
                'surname' => $this->surname,
                'type' => UserTypeEnum::Athlete,
                'extra' => $extra,
            ]);
            $this->id = $user->id;
        } else {
            $user = User::findOrFail($this->id);
            $user->forename = $this->forename;
            $user->surname = $this->surname;
            $user->extra = $extra;
            $user->save();
        }
    }

    public static function example(int $id = 1, string $forename = 'John', string $surname = 'Doe'): self
    {
        return new self(
            id: $id,
            forename: $forename,
            surname: $surname,
        );
    }

    public static function getFields(): array
    {
        return [
            FluxField::text('forename')
                ->label('Forename')
                ->placeholder('Forename')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            FluxField::text('surname')
                ->label('Surname')
                ->placeholder('Surname')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
        ];
    }
}
