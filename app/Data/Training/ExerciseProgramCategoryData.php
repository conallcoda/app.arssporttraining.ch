<?php

namespace App\Data\Training;

use App\Form\Fields\Training\Program\Color;
use App\Models\Exercise\ExerciseProgramCategory;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseProgramCategoryData extends AbstractData implements HasForms
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

        if ($data instanceof ExerciseProgramCategory) {
            return self::fromModel($data);
        }

        return new static(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            color: $data['color'] ?? null,
            sort: (int) ($data['sort'] ?? 0),
        );
    }

    public static function fromModel(ExerciseProgramCategory $category): static
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
        $category = ExerciseProgramCategory::updateOrCreate(
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
