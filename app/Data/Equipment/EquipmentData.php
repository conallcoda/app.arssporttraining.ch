<?php

namespace App\Data\Equipment;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields\Text;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Models\Tag;
use Carbon\Carbon;

class EquipmentData extends AbstractData implements HasForms
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
                'scope' => 'exercise_equipment',
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
