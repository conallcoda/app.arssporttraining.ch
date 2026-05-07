<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text;
use Coda\FormKit\Form;
use Illuminate\Database\Eloquent\Model;

class CmsTestLeafData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id = null,
        public ?int $groupId = null,
        public string $name = '',
    ) {}

    public static function fromModel(Model $model): self
    {
        /** @var CmsTestLeaf $model */
        return new self(
            id: $model->id,
            groupId: $model->group_id,
            name: $model->name,
        );
    }

    public function persist(): Model
    {
        $leaf = CmsTestLeaf::query()->updateOrCreate(
            ['id' => $this->id],
            [
                'group_id' => $this->groupId,
                'name' => $this->name,
            ],
        );

        $this->id = $leaf->id;

        return $leaf;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Select::make('groupId')
                    ->label('Group')
                    ->options(CmsTestItem::query()->orderBy('name')->pluck('name', 'id')->all()),
                Text::make('name')->label('Name')->required(),
            ]);
    }
}
