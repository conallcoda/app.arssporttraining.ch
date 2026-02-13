<?php

namespace App\Data\Training;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields\Text;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Training\Program\Color;
use App\Models\ProgramCategory;

class ProgramCategoryData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $color = null,
        public int $sort = 0,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = $payloads[0] ?? $payloads;

        if ($data instanceof ProgramCategory) {
            return self::fromModel($data);
        }

        return new static(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            color: $data['color'] ?? null,
            sort: (int) ($data['sort'] ?? 0),
        );
    }

    public static function fromModel(ProgramCategory $category): static
    {
        return new static(
            id: $category->id,
            name: $category->name,
            color: $category->color,
            sort: $category->sort,
        );
    }

    public function persist(): void
    {
        $category = ProgramCategory::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'color' => $this->color,
                'sort' => $this->sort,
            ]
        );

        $this->id = $category->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
                Color::make('color'),
            ]);
    }
}
