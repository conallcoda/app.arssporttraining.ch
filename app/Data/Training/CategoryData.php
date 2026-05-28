<?php

namespace App\Data\Training;

use App\Form\Fields\Category as CategoryField;
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

    public array $children = [];

    public int $depth = 0;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $shortName = null,
        public ?string $color = null,
        public ?int $parentId = null,
        public int $sortOrder = 0,
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
            parentId: $tag->parent_id,
            sortOrder: $tag->sort_order ?? 0,
            defaultExerciseTemplate: $tag->default_exercise_template_id,
            updatedAt: $tag->updated_at,
        );
    }

    public static function fromTagTree(Tag $tag, int $depth = 0): self
    {
        $children = [];

        if ($tag->relationLoaded('children')) {
            $children = $tag->children
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (Tag $child) => self::fromTagTree($child, $depth + 1))
                ->all();
        }

        $data = self::fromTag($tag);
        $data->children = $children;
        $data->depth = $depth;

        return $data;
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
            parentId: $data['parentId'] ?? null,
            sortOrder: (int) ($data['sortOrder'] ?? 0),
            defaultExerciseTemplate: $data['defaultExerciseTemplate'] ?? null,
        );
    }

    public function persist(): void
    {
        $parentId = $this->parentId;

        if ($this->id !== null && $parentId !== null) {
            $current = Tag::find($this->id);

            if ($current !== null && $current->descendantsAndSelf()->whereKey($parentId)->exists()) {
                $parentId = $current->parent_id;
            }
        }

        $tag = Tag::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'short_name' => $parentId === null && $this->shortName ? strtoupper($this->shortName) : null,
                'color' => $this->color,
                'default_exercise_template_id' => $this->defaultExerciseTemplate,
                'scope' => 'exercise_category',
                'parent_id' => $parentId,
                'sort_order' => $this->sortOrder,
            ]
        );

        $this->id = $tag->id;
        $this->parentId = $parentId;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
                CategoryField::make('parentId', 'exercise_category')
                    ->label('Parent')
                    ->placeholder('No parent')
                    ->withOptions(),
                Text::make('shortName')
                    ->label('Short Name')
                    ->maxLength(4)
                    ->uppercase()
                    ->rules('nullable|max:4|alpha')
                    ->show('parentId == null'),
                Color::make('color')
                    ->show('parentId == null'),
                Select::make('defaultExerciseTemplate')
                    ->label('Default Template')
                    ->options(ExerciseTemplate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->variant('listbox')
                    ->searchable()
                    ->clearable(),
            ]);
    }
}
