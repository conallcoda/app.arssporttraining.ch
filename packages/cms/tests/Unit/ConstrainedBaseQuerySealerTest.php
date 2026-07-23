<?php

namespace Coda\Cms\Tests\Unit;

use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Tests\TestCase;

class ConstrainedBaseQuerySealerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('cms_scoped_query_builder_records');

        Schema::create('cms_scoped_query_builder_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('conference_edition_id');
            $table->string('name');
        });

        CmsScopedQueryBuilderRecord::query()->insert([
            ['id' => 1, 'conference_edition_id' => 2026, 'name' => 'Alice'],
            ['id' => 2, 'conference_edition_id' => 2026, 'name' => 'Bob'],
            ['id' => 3, 'conference_edition_id' => 2025, 'name' => 'Alice'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('cms_scoped_query_builder_records');

        parent::tearDown();
    }

    public function test_it_keeps_callback_filters_inside_the_scoped_base_query(): void
    {
        $queryBuilder = CmsScopedQueryBuilderRecord::buildQueryBuilder(
            CmsScopedQueryBuilderRecord::query()->where('conference_edition_id', 2026),
            Request::create('/', 'GET', ['filter' => ['search' => 'alice']]),
        );

        $queryBuilder->allowedFilters([
            AllowedFilter::callback('search', function ($query): void {
                $query->where('name', 'Missing')
                    ->orWhere('name', 'Alice');
            }),
        ]);

        $this->assertSame([1], $queryBuilder->pluck('id')->all());
    }

    public function test_it_preserves_sorting_on_the_sealed_scoped_dataset(): void
    {
        $queryBuilder = CmsScopedQueryBuilderRecord::buildQueryBuilder(
            CmsScopedQueryBuilderRecord::query()->where('conference_edition_id', 2026),
            Request::create('/', 'GET', ['sort' => '-name']),
        );

        $queryBuilder->allowedSorts(['name']);

        $this->assertSame([2, 1], $queryBuilder->pluck('id')->all());
    }
}

class CmsScopedQueryBuilderRecord extends Model
{
    use HasQueryBuilder;

    public $timestamps = false;

    protected $table = 'cms_scoped_query_builder_records';

    protected $guarded = [];
}
