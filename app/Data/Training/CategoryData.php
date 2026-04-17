<?php

namespace App\Data\Training;

use App\Form\Fields\Training\Program\Color;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text;
use Coda\FormKit\Form;

class CategoryData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $shortName = null,
        public ?string $color = null,
        public ?int $defaultExerciseTemplate = null,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTag(Tag $tag): self
    {
        return new self(
            id: $tag->id,
            name: $tag->name,
            shortName: $tag->short_name,
            color: $tag->color,
            defaultExerciseTemplate: $tag->default_exercise_template_id,
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
            shortName: $data['shortName'] ?? null,
            color: $data['color'] ?? null,
            defaultExerciseTemplate: $data['defaultExerciseTemplate'] ?? null,
        );
    }

    public function persist(): void
    {
        $tag = Tag::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'short_name' => $this->shortName ? strtoupper($this->shortName) : null,
                'color' => $this->color,
                'default_exercise_template_id' => $this->defaultExerciseTemplate,
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
                Text::make('shortName')->label('Short Name')->maxLength(4)->uppercase()->rules('nullable|max:4|alpha'),
                Color::make('color'),
                Select::make('defaultExerciseTemplate')
                    ->label('Default Template')
                    ->options(ExerciseTemplate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->variant('listbox')
                    ->searchable()
                    ->clearable(),
            ]);
    }
}
