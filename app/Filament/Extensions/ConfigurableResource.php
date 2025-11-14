<?php

namespace App\Filament\Extensions;

use App\Filament\Extensions\Form\ConfigurableForm;
use App\Filament\Extensions\List\ConfigurableTable;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Str;

abstract class ConfigurableResource extends Resource
{
    abstract protected static function configure(): array;

    protected static function formConfig(): ?array
    {
        return null;
    }

    protected static function tableConfig(): ?array
    {
        return null;
    }

    protected static function getDefaultLabel(): string
    {
        $modelClass = static::getModel();
        $basename = class_basename($modelClass);
        return Str::title(Str::snake($basename, ' '));
    }

    protected static function getDefaultPluralLabel(): string
    {
        return Str::plural(static::getDefaultLabel());
    }

    public static function getModel(): string
    {
        return static::configure()['model'];
    }

    public static function getNavigationGroup(): ?string
    {
        return static::configure()['navigationGroup'] ?? null;
    }

    public static function getNavigationIcon(): ?string
    {
        return static::configure()['navigationIcon'] ?? null;
    }

    public static function getNavigationLabel(): string
    {
        return static::configure()['navigationLabel'] ?? static::getDefaultPluralLabel();
    }

    public static function getModelLabel(): string
    {
        return static::configure()['modelLabel'] ?? static::getDefaultLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::configure()['pluralModelLabel'] ?? static::getDefaultPluralLabel();
    }

    public static function getBreadcrumb(): string
    {
        return static::configure()['breadcrumb'] ?? static::getDefaultPluralLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return static::configure()['navigationSort'] ?? null;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        $form = static::configure()['form'] ?? null;

        if ($form) {
            if ($form instanceof Schema) {
                return $form;
            }

            return $form::configure($schema);
        }

        $formConfig = static::formConfig();

        if ($formConfig) {
            return ConfigurableForm::buildFromConfig($schema, $formConfig);
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        $tableClass = static::configure()['table'] ?? null;

        if ($tableClass) {
            if ($tableClass instanceof Table) {
                return $tableClass;
            }

            return $tableClass::configure($table);
        }

        $tableConfig = static::tableConfig();

        if ($tableConfig) {
            return ConfigurableTable::buildFromConfig($table, $tableConfig);
        }

        return $table;
    }

    public static function getPages(): array
    {
        $pagesConfig = static::configure()['pages'] ?? [];
        $pages = [];

        if (isset($pagesConfig['index'])) {
            $indexConfig = is_array($pagesConfig['index']) ? $pagesConfig['index'] : [];
            $pages['index'] = ConfigurableListRecords::configure([
                'resource' => static::class,
                ...$indexConfig,
            ])::route('/');
        }

        if ($pagesConfig['create'] ?? false) {
            $pages['create'] = ConfigurableCreateRecord::configure([
                'resource' => static::class,
            ])::route('/create');
        }

        if ($pagesConfig['edit'] ?? false) {
            $pages['edit'] = ConfigurableEditRecord::configure([
                'resource' => static::class,
            ])::route('/{record}/edit');
        }

        return $pages;
    }
}
