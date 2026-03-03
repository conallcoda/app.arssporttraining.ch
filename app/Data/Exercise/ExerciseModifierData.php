<?php

namespace App\Data\Exercise;

use App\Models\Tag;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseModifierData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTag(Tag $tag): self
    {
        return new self(
            id: $tag->id,
            name: $tag->name,
            updatedAt: $tag->updated_at,
        );
    }

    public function persist(): void
    {
        $tag = Tag::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'scope' => 'exercise_modifiers',
            ]
        );

        $this->id = $tag->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
            ]);
    }
}
