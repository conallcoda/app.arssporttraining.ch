<?php

namespace Coda\Cms\Tests\Unit;

use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class AbstractModelListFilterContextTest extends TestCase
{
    public function test_it_exposes_scope_context_data_to_filter_fields(): void
    {
        $component = new FilterContextTestComponent;
        $component->filters = [
            'search' => 'nicolo',
        ];

        $this->assertSame([
            'conference_edition_id' => 2026,
            'filters' => [
                'search' => 'nicolo',
            ],
        ], $component->filterFieldContextData());
    }
}

class FilterContextTestComponent extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return FilterContextTestRecordData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return FilterContextTestRecord::query();
    }

    protected function getTable(): Table
    {
        return Table::make();
    }

    protected function getFormContextData(): array
    {
        return [
            'conference_edition_id' => 2026,
        ];
    }
}

class FilterContextTestRecord extends Model
{
    public $timestamps = false;

    protected $table = 'scoped_test_records';

    protected $guarded = [];
}

class FilterContextTestRecordData
{
    public static function fromModel(Model $model): static
    {
        return new static;
    }
}
