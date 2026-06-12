<?php

namespace Coda\Cms\Tests\Unit;

use Coda\Cms\AdminModule;
use Coda\SchemaKit\Entity;
use Coda\SchemaKit\ScopeDefinition;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class AdminModuleScopeResolutionTest extends TestCase
{
    public function test_it_applies_scopes_from_the_component_snapshot_when_building_queries(): void
    {
        $module = $this->scopedModule();
        $component = new ScopedTestComponent(2026);

        $query = $module->query($component);

        $this->assertSame([
            [
                'type' => 'Basic',
                'column' => 'conference_edition_id',
                'operator' => '=',
                'value' => 2026,
                'boolean' => 'and',
            ],
        ], $query->getQuery()->wheres);
    }

    public function test_it_uses_the_component_context_for_scope_bound_form_defaults(): void
    {
        $module = $this->scopedModule();
        $bindings = $module->contextBindingsData();

        $this->assertArrayHasKey('conference_edition_id', $bindings);
        $this->assertSame(
            2026,
            $bindings['conference_edition_id']((object) ['id' => 2026], new ScopedTestComponent(9999)),
        );
    }

    protected function scopedModule(): AdminModule
    {
        return AdminModule::make('scoped.records')
            ->schema(
                Entity::make('record')
                    ->model(ScopedTestRecord::class)
                    ->scope(
                        ScopeDefinition::make('conference_edition')
                            ->attribute('conference_edition_id')
                            ->field('conference_edition_id')
                    )
            )
            ->recordModel(ScopedTestRecord::class)
            ->dataClass(ScopedTestRecordData::class)
            ->scopedTo('conference_edition');
    }
}

class ScopedTestComponent
{
    public function __construct(
        private readonly int $scopeId,
    ) {}

    public function scopeValue(string $path = 'id', mixed $default = null): mixed
    {
        return $path === 'id' ? $this->scopeId : $default;
    }
}

class ScopedTestRecord extends Model
{
    public $timestamps = false;

    protected $table = 'scoped_test_records';

    protected $guarded = [];
}

class ScopedTestRecordData
{
    public static function fromModel(Model $model): static
    {
        return new static;
    }
}
