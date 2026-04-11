<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\Date;
use Coda\FormKit\Fields\Number;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\SwitchField;
use Coda\FormKit\Fields\Text;
use Coda\FormKit\Form;
use Illuminate\Database\Eloquent\Model;

class CmsTestItemData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public array $ancestorIds = [];

    public bool $isFirstSibling = false;

    public bool $isLastSibling = false;

    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public ?string $status = 'draft',
        public int $priority = 0,
        public ?int $parentId = null,
        public bool $is_active = true,
        public ?string $published_at = null,
        public array $children = [],
        public int $depth = 0,
    ) {}

    public static function fromModel(Model $model, int $depth = 0): self
    {
        $children = [];
        if ($model->relationLoaded('children')) {
            $children = $model->children
                ->map(fn (Model $child) => self::fromModel($child, $depth + 1))
                ->all();
        }

        return new self(
            id: $model->id,
            name: $model->name ?? '',
            status: $model->status,
            priority: $model->priority ?? 0,
            parentId: $model->parent_id,
            is_active: (bool) $model->is_active,
            published_at: $model->published_at?->format('Y-m-d'),
            children: $children,
            depth: $depth,
        );
    }

    public function persist(): void
    {
        $item = CmsTestItem::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'status' => $this->status,
                'priority' => $this->priority,
                'parent_id' => $this->parentId,
                'is_active' => $this->is_active,
                'published_at' => $this->published_at,
            ]
        );

        $this->id = $item->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
                Select::make('status')->label('Status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
                Number::make('priority')->label('Priority')
                    ->show('status == "published"'),
                SwitchField::make('is_active')->label('Active'),
                Date::make('published_at')->label('Published At'),
            ]);
    }
}
