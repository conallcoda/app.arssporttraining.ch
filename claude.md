# Athlete Training Platform - Software Stack

## Important

Never add comments inline or to methods or classes

## Overview
This is an athlete training management application built with Laravel and Filament, focusing on exercise management, equipment tracking, and muscle group categorization.

## Core Framework & Runtime

### Backend
- **Laravel Framework**: v12.37.0 (Latest Laravel 12)
- **PHP**: v8.4.10 (requires ^8.2)
- **Livewire**: For reactive components

### Frontend
- **Alpine.js**: v3.15.0 - Lightweight JavaScript framework
- **Vite**: v7.0.4 - Build tool and dev server
- **Tailwind CSS**: v4.1.17 - Utility-first CSS framework

## Admin Panel & UI

### Filament (Admin Panel)
- **Filament**: v4.0 - Laravel admin panel framework
- **Filament Badgeable Column**: v3.0 (awcodes/filament-badgeable-column)
- **Filament Sticky Header**: v3.0 (awcodes/filament-sticky-header)
- **Filament Media Library Plugin**: v4.2 (Spatie integration)

### Icons & Styling
- **Blade Lucide Icons**: v1.23 (mallardduck/blade-lucide-icons)
- **Tailwind Typography**: v0.5.19
- **Autoprefixer**: v10.4.20

## Key Laravel Packages

### Media & File Management
- **Spatie Laravel Media Library**: v11.17 - Handle media files and uploads
- **Spatie Laravel Media Library Plugin**: v4.2 (Filament integration)

### Data Management
- **Spatie Laravel Data**: v4.18 - Data transfer objects
- **Spatie Laravel Schemaless Attributes**: v2.5 - Store unstructured data

### Database & Models
- **Tightenco Parental**: v1.4 - Single table inheritance for Eloquent

## Development Workflow

### Setup Command
```bash
composer setup
```
This runs: install dependencies, copy .env, generate key, migrate, install npm packages, and build assets

### Development Server
```bash
composer dev
```
Runs concurrently:
- PHP Artisan serve (server)
- Queue listener
- Laravel Pail (logs)
- Vite dev server

### Testing
```bash
composer test
```
Runs Pest PHP test suite

## Project Structure

This appears to be an exercise/fitness management application with:
- Exercise management (exercises, equipment, muscles)
- Filament resources for admin panel
- Media library integration for exercise images/videos
- Custom import commands for exercise data

## Key Features

- Admin panel built with Filament 4
- Exercise database with equipment and muscle tracking
- Media management for exercise demonstrations
- Reactive UI components with Livewire
- Modern build pipeline with Vite
- Comprehensive testing setup with Pest

## Filament Resource Conventions

This project follows consistent patterns for creating Filament resources. Choose the appropriate pattern based on resource complexity.

### Simple Resources (Single-Page CRUD)

Use this pattern for models with simple fields (1-3 fields), no complex relationships, and when inline editing is preferred.

**Examples:** ExerciseEquipment, ExerciseMuscles

**Directory Structure:**
```
app/Filament/Resources/
└── {ModelName}/
    ├── {ModelName}Resource.php
    └── Pages/
        └── Manage{PluralModelName}.php
```

**Resource Class Pattern:**
```php
class ExerciseEquipmentResource extends Resource
{
    protected static ?string $model = ExerciseEquipment::class;

    // Navigation
    protected static UnitEnum|string|null $navigationGroup = 'Exercises';
    protected static string|BackedEnum|null $navigationIcon = 'lucide-weight';
    protected static ?string $navigationLabel = 'Equipment';
    protected static ?string $modelLabel = 'Equipment';
    protected static ?string $pluralModelLabel = 'Equipment';
    protected static ?string $breadcrumb = 'Equipment';
    protected static ?int $navigationSort = 2;

    // Navigation badge showing count
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    // Form defined inline
    public static function form(Schema $schema): Schema
    {
        return $schema->components([...]);
    }

    // Table defined inline with inline editing
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextInputColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([...])
            ->recordActions([EditAction::make()]);
    }

    // Single page
    public static function getPages(): array
    {
        return [
            'index' => ManageExerciseEquipment::route('/'),
        ];
    }

    // Support soft deletes
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
```

**ManageRecords Page:**
```php
class ManageExerciseEquipment extends ManageRecords
{
    protected static string $resource = ExerciseEquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()]; // Use custom CreateAction
    }
}
```

### Complex Resources (Multi-Page CRUD)

Use this pattern for models with many fields, complex relationships, nested sections, or custom creation/update logic.

**Examples:** Exercise

**Directory Structure:**
```
app/Filament/Resources/
└── {Domain}/
    └── {PluralModelName}/
        ├── {ModelName}Resource.php
        ├── Pages/
        │   ├── List{PluralModelName}.php
        │   ├── Create{ModelName}.php
        │   └── Edit{ModelName}.php
        ├── Schemas/
        │   └── {ModelName}Form.php
        └── Tables/
            └── {PluralModelName}Table.php
```

**Resource Class Pattern:**
```php
class ExerciseResource extends Resource
{
    protected static ?string $model = Exercise::class;

    // Navigation (same as simple resources)
    protected static UnitEnum|string|null $navigationGroup = 'Exercises';
    protected static string|BackedEnum|null $navigationIcon = 'lucide-dumbbell';
    // ... other navigation properties

    // Form delegated to separate class
    public static function form(Schema $schema): Schema
    {
        return ExerciseForm::configure($schema);
    }

    // Table delegated to separate class
    public static function table(Table $table): Table
    {
        return ExercisesTable::configure($table);
    }

    // Multiple pages
    public static function getPages(): array
    {
        return [
            'index' => ListExercises::route('/'),
            'create' => CreateExercise::route('/create'),
            'edit' => EditExercise::route('/{record}/edit'),
        ];
    }

    // Support soft deletes
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
```

**Form Class (Schemas/):**
```php
class ExerciseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Information')
                ->schema([...])
                ->columns(2),

            Section::make('Classification')
                ->schema([...])
                ->columns(2),

            Section::make('Muscle Groups')
                ->schema([
                    Select::make('primaryMuscles')
                        ->relationship('primaryMuscles', 'name')
                        ->multiple()
                        ->native(false)
                        ->createOptionForm([...]), // Inline creation
                ])
                ->columns(2),
        ]);
    }
}
```

**Table Class (Tables/):**
```php
class ExercisesTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([...])
            ->filters([...])
            ->defaultSort('name');
    }
}
```

**List Page with Tabs:**
```php
class ListExercises extends AbstractListRecords
{
    protected static string $resource = ExerciseResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn() => Exercise::count()),
            'strength' => Tab::make('Strength')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'strength'))
                ->badge(fn() => StrengthExercise::count()),
        ];
    }
}
```

**Create Page:**
```php
class CreateExercise extends AbstractCreateRecord
{
    protected static string $resource = ExerciseResource::class;

    // Custom creation logic (e.g., for single-table inheritance)
    protected function handleRecordCreation(array $data): Exercise
    {
        $type = $data['type'] ?? 'strength';
        $childTypes = (new Exercise())->getChildTypes();
        $modelClass = $childTypes[$type] ?? Exercise::class;
        return $modelClass::create($data);
    }
}
```

**Edit Page:**
```php
class EditExercise extends AbstractEditRecord
{
    protected static string $resource = ExerciseResource::class;

    // Custom header actions (Cancel/Save)
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    // Custom form actions (Delete/Restore)
    protected function getFormActions(): array
    {
        return [
            DeleteAction::make()->extraAttributes(['class' => 'ml-auto']),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    // Custom update logic (e.g., for type changes)
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        if (isset($data['type']) && $data['type'] !== $record->type) {
            return Exercise::find($record->id);
        }
        return $record;
    }
}
```

### Abstract Base Classes

All pages extend custom abstract classes for consistency:

**Tables:** `app/Filament/Tables/AbstractTable.php`
- Provides `applyDefaults()` with standard actions and pagination
- Default record actions: `EditAction`
- Default bulk actions: `DeleteBulkAction`, `ForceDeleteBulkAction`, `RestoreBulkAction`

**Pages:**
- `AbstractListRecords` - Provides `CreateAction` in header
- `AbstractCreateRecord` - Provides standard title
- `AbstractEditRecord` - Provides delete actions in header

### Custom Actions

**CreateAction:** `app/Filament/Actions/CreateAction.php`
- Custom create action with simplified "Create" label
- Used in ManageRecords pages

### Common Conventions

**Navigation:**
- Always set `$navigationGroup` to group related resources
- Use Lucide icons (e.g., `lucide-dumbbell`, `lucide-weight`)
- Implement `getNavigationBadge()` to show record counts
- Set `$navigationSort` to control menu order

**Soft Deletes:**
- All resources support soft deletes
- Override `getRecordRouteBindingEloquentQuery()` to include trashed records

**Inline Editing (Simple Resources):**
- Use `TextInputColumn` for editable columns
- Always add `searchable()` and `sortable()` for text fields

**Relationships:**
- Use `createOptionForm()` for inline creation of related records
- Use `native(false)` for custom select styling
- Use `multiple()` for many-to-many relationships

**Naming Conventions:**
- Resources: `{ModelName}Resource`
- Pages: `Manage{PluralModelName}`, `List{PluralModelName}`, `Create{ModelName}`, `Edit{ModelName}`
- Forms: `{ModelName}Form`
- Tables: `{PluralModelName}Table`

## License
MIT
