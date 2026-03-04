<?php

namespace App\Data\Training;

use App\Models\Tag;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Select;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;
use Coda\Cms\Support\ColorPalette;

class CategoryData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $color = null,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTag(Tag $tag): self
    {
        return new self(
            id: $tag->id,
            name: $tag->name,
            color: $tag->color,
            updatedAt: $tag->updated_at,
        );
    }

    public static function from(mixed ...$payloads): static
    {
        $data = $payloads[0] ?? $payloads;

        if ($data instanceof Tag) {
            return self::fromTag($data);
        }

        return new static(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            color: $data['color'] ?? null,
        );
    }

    public function persist(): void
    {
        $tag = Tag::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'color' => $this->color,
                'scope' => 'exercise_category',
                'parent_id' => null,
            ]
        );

        $this->id = $tag->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
                Select::make('color')->label('Color')->placeholder('Select a color...')->options(ColorPalette::COLORS),
            ]);
    }
}
