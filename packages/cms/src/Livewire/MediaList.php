<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\MediaData;
use Coda\Cms\Display\CardDefinition;
use Coda\Cms\Display\CardField;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Spatie\MediaLibrary\HasMedia;

final class MediaList extends AbstractModelList
{
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int|string $modelId;

    /** @var list<string> */
    #[Locked]
    public array $collections = [];

    #[Locked]
    public bool $imagesOnly = false;

    public function mount(...$routeParameters): void
    {
        $modelClass = $routeParameters['modelClass'] ?? $routeParameters[0] ?? null;
        $modelId = $routeParameters['modelId'] ?? $routeParameters[1] ?? null;
        $collections = $routeParameters['collections'] ?? $routeParameters[2] ?? [];
        $imagesOnly = $routeParameters['imagesOnly'] ?? $routeParameters[3] ?? false;

        if (is_bool($collections)) {
            $imagesOnly = $collections;
            $collections = [];
        }

        abort_unless(
            is_string($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && is_subclass_of($modelClass, HasMedia::class),
            404,
        );
        abort_unless(is_int($modelId) || is_string($modelId), 404);
        abort_unless(is_array($collections), 404);

        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->collections = array_values(array_filter($collections, 'is_string'));
        $this->imagesOnly = (bool) $imagesOnly;

        parent::mount();
    }

    protected function getDataClass(): string
    {
        return MediaData::class;
    }

    protected function getBaseQuery(): Builder
    {
        $owner = $this->modelClass::query()->findOrFail($this->modelId);
        $mediaClass = config('media-library.media_model', \Coda\Cms\Models\Media::class);

        return $mediaClass::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->when(
                $this->collections !== [],
                fn (Builder $query) => $query->whereIn('collection_name', $this->collections),
            )
            ->when(
                $this->imagesOnly,
                fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'),
            );
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make('id'),
                Text::make('name')->label('Name')->title(),
                Text::make('type')->label('Type'),
                Text::make('size_label')->label('Size'),
                Text::make('uuid')->label('UUID'),
            ])
            ->cardDefinition(
                CardDefinition::make()
                    ->title(CardField::make('name')->hideLabel())
                    ->image(CardField::make('preview_url')->hideLabel()->aspect('video'))
                    ->meta([
                        CardField::make('type')->label('Type'),
                        CardField::make('size_label')->label('Size'),
                    ])
                    ->view('cms::media-card')
            )
            ->cardView('cms::media-card')
            ->cardWidth(240)
            ->defaultView('cards')
            ->showViewToggle(false)
            ->defaultSort('name');
    }

    protected function getActions(): array
    {
        return [];
    }

    protected function getDefaultOptions(): array
    {
        return [
            ...parent::getDefaultOptions(),
            'showAddButton' => false,
            'showFilters' => false,
            'showPagination' => false,
            'compact' => true,
        ];
    }

    protected function urlPrefix(): string
    {
        return 'media_'.substr(md5($this->modelClass), 0, 8).'_'.$this->modelId.'_';
    }
}
