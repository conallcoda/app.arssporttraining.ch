<?php

namespace App\Data\Athlete;

use App\Form\Fields\Athlete\DateOfBirth;
use App\Form\Fields\Athlete\Email;
use App\Form\Fields\Athlete\Forename;
use App\Form\Fields\Athlete\Gender;
use App\Form\Fields\Athlete\Phone;
use App\Form\Fields\Athlete\Surname;
use App\Form\Fields\Owner;
use App\Models\Users\GenderEnum;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields\Tags;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;

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
        public array $internalTags = [],
        public ?int $owner_id = null,
        public ?string $ownerName = null,
        public ?string $ownerColor = null,
        public ?Carbon $updatedAt = null,
        public string $personName = '',
        public array $metrics = [],
    ) {}

    public function name(): string
    {
        return trim("{$this->forename} {$this->surname}");
    }

    /** @return list<array{label: string}> */
    public function getMetricBadges(): array
    {
        return $this->metrics;
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
            dateOfBirth: $user->date_of_birth?->format('d.m.Y'),
            internalTags: $user->relationLoaded('internalTags') ? $user->internalTags->pluck('id')->all() : [],
            owner_id: $user->owner_id ?? 0,
            ownerName: $user->relationLoaded('owner') ? $user->owner?->name : null,
            ownerColor: $user->relationLoaded('owner') ? $user->owner?->color : null,
            updatedAt: $user->updated_at,
            personName: trim(($user->surname ?? '').', '.($user->forename ?? ''), ', '),
            metrics: $user->relationLoaded('metricSubmissions')
                ? $user->metricSubmissions->map(function ($submission) use ($user) {
                    $metricClass = $submission->metric->metricClass();
                    $fieldValues = $submission->values->pluck('value', 'field')->all();
                    $metric = $metricClass::from($fieldValues);
                    $badge = $metric->badge($submission->metric->shortLabel());
                    $badge['url'] = route('athlete-metric-index', ['athleteId' => $user->id])
                        .'?am_selectedTab='.$submission->metric->value;

                    return $badge;
                })->all()
                : [],
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
                'date_of_birth' => $this->dateOfBirth ? Carbon::createFromFormat('d.m.Y', $this->dateOfBirth)->format('Y-m-d') : null,
                'type' => UserTypeEnum::Athlete,
                'owner_id' => $this->owner_id,
                'config' => [],
            ]
        );

        $this->id = $user->id;

        $user->tags()->sync($this->internalTags);
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Owner::make('owner_id')->withOptions()->allowUnassigned(),
                Forename::make('forename'),
                Surname::make('surname'),
                Email::make('email'),
                Phone::make('phone'),
                Gender::make('gender'),
                DateOfBirth::make('dateOfBirth'),
                Tags::make('internalTags', 'athlete_internal')->label('Tags')->withOptions()->create(),
            ]);
    }
}
