<?php

namespace App\Filament\Pages;

use App\Models\Exercise\Exercise;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Schema;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Support\RawJs;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExerciseDefaultSettings extends AbstractPageSettings
{
    public ?array $data = [];

    #[Url]
    public string $filter = 'all';

    public function getIncompleteExerciseIds(): array
    {
        $data = $this->data['exercise_defaults'] ?? [];

        return collect($data)
            ->filter(function ($row) {
                return empty($row['orm_conversion'])
                    || empty($row['sets'])
                    || empty($row['tut'])
                    || empty($row['rest']);
            })
            ->pluck('exercise_id')
            ->toArray();
    }

    protected static ?string $title = 'Exercise Defaults';

    protected static ?int $navigationSort = 101;

    protected string $view = 'filament.pages.exercise-default-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Training';
    }

    protected function settingName(): string
    {
        return 'exercise_defaults';
    }

    public function getDefaultData(): array
    {
        $exercises = Exercise::where('type', 'strength')
            ->orderBy('name')
            ->get();

        return [
            'exercise_defaults' => $exercises->map(fn($exercise) => [
                'exercise_id' => $exercise->id,
                'orm_conversion' => null,
                'sets' => null,
                'tut' => null,
                'rest' => null,
            ])->toArray(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TableRepeater::make('exercise_defaults')
                    ->reorderable(false)
                    ->cloneable(false)
                    ->label('Exercise Defaults')
                    ->schema([
                        Select::make('exercise_id')
                            ->label('Exercise')
                            ->options(
                                Exercise::where('type', 'strength')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('orm_conversion')
                            ->label('Default 1RM (Back Squat %)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(999)
                            ->suffix('%'),

                        TextInput::make('sets')
                            ->label('Default Sets (Block 1, Week 1)')
                            ->mask(RawJs::make(<<<'JS'
                                (() => {
                                    const digits = $input.replace(/[^0-9]/g, '').length;
                                    const pairs = Math.min(Math.ceil(digits / 2) || 1, 6);
                                    return Array(pairs).fill('99').join('-');
                                })()
                            JS))
                            ->placeholder('10-10-10-08'),

                        TextInput::make('tut')
                            ->label('Default TUT')
                            ->mask('99-99-99-99')
                            ->placeholder('03-01-03-01'),

                        TextInput::make('rest')
                            ->label('Default Rest (seconds)')
                            ->numeric()
                            ->integer()
                            ->suffix('seconds'),

                    ])
                    ->addable(false)
                    ->collapsible()
                    ->deletable(false)
                    ->columnSpan('full')
                    ->afterStateHydrated(function (TableRepeater $component, $state) {
                        $exercises = Exercise::where('type', 'strength')
                            ->orderBy('name')
                            ->get();

                        $existingIds = collect($state ?? [])->pluck('exercise_id')->filter()->toArray();

                        $updatedState = $state ?? [];

                        foreach ($exercises as $exercise) {
                            if (!in_array($exercise->id, $existingIds)) {
                                $updatedState[] = [
                                    'exercise_id' => $exercise->id,
                                    'orm_conversion' => null,
                                    'sets' => null,
                                    'tut' => null,
                                    'rest' => null,
                                ];
                            }
                        }

                        $exerciseOrder = $exercises->pluck('id')->toArray();
                        usort($updatedState, function ($a, $b) use ($exerciseOrder) {
                            $posA = array_search($a['exercise_id'], $exerciseOrder);
                            $posB = array_search($b['exercise_id'], $exerciseOrder);
                            return $posA <=> $posB;
                        });

                        $component->state($updatedState);
                    }),
            ])
            ->statePath('data');
    }

    public function export(): StreamedResponse
    {
        $exercises = Exercise::where('type', 'strength')
            ->orderBy('name')
            ->pluck('name', 'id');

        $data = $this->data['exercise_defaults'] ?? [];

        return response()->streamDownload(function () use ($data, $exercises) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Exercise', 'Default 1RM (Back Squat %)', 'Default Sets (Block 1, Week 1)', 'Default TUT', 'Default Rest (seconds)']);

            foreach ($data as $row) {
                fputcsv($handle, [
                    $exercises[$row['exercise_id']] ?? '',
                    $row['orm_conversion'] ?? '',
                    $row['sets'] ?? '',
                    $row['tut'] ?? '',
                    $row['rest'] ?? '',
                ]);
            }

            fclose($handle);
        }, 'exercise-defaults.csv');
    }
}
