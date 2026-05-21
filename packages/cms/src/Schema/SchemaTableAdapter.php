<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Display\DisplayField;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\Date as DisplayDate;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Number as DisplayNumber;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\IdentityImageDefinition;
use Coda\SchemaKit\ResolvedViewDefinition;
use Coda\SchemaKit\SchemaRegistry;
use Coda\SchemaKit\TableColumnDefinition;
use RuntimeException;

class SchemaTableAdapter
{
    public function __construct(
        private readonly SchemaRegistry $registry,
    ) {}

    public function table(string $entityName, string $viewName): Table
    {
        $resolved = $this->registry->resolveView($entityName, $viewName);
        $table = $resolved->view()->getTable();
        $tableConfig = $resolved->view()->getMeta('table', []);

        if ($table !== null) {
            $tableConfig = [
                'columns' => $table->getColumns(),
                'sortable' => $table->getSortable(),
                'filters' => $table->getFilters(),
                'default_sort' => $table->getDefaultSort(),
            ];
        } elseif (! is_array($tableConfig) || $tableConfig === []) {
            $tableConfig = [
                'columns' => $resolved->view()->getMeta('show_fields', []),
                'sortable' => $resolved->view()->getMeta('sortable', []),
                'filters' => $resolved->view()->getMeta('filters', []),
                'default_sort' => $resolved->view()->getMeta('default_sort'),
            ];
        }

        $instance = Table::make()->columns($this->tableColumns($resolved, $tableConfig['columns'] ?? []));

        $sortable = $tableConfig['sortable'] ?? [];

        if (is_array($sortable) && $sortable !== []) {
            $instance->sortable($sortable);
        }

        $filters = $tableConfig['filters'] ?? [];

        if (is_callable($filters)) {
            $filters = $filters();
        }

        if (is_array($filters) && $filters !== []) {
            $instance->filters($filters);
        }

        $defaultSort = $tableConfig['default_sort'] ?? null;

        if (is_array($defaultSort) && isset($defaultSort['field'])) {
            $instance->defaultSort(
                (string) $defaultSort['field'],
                (string) ($defaultSort['direction'] ?? 'asc'),
            );
        }

        return $instance;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $columnConfigs
     * @return array<int, DisplayField>
     */
    private function tableColumns(ResolvedViewDefinition $resolved, array $columnConfigs): array
    {
        $columns = [];

        foreach ($columnConfigs as $config) {
            if ($config instanceof TableColumnDefinition) {
                $columns[] = $this->typedColumn($resolved, $config);

                continue;
            }

            if (is_string($config)) {
                if ($config === '@id') {
                    $columns[] = Id::make();
                    continue;
                }

                if ($config === '@identity') {
                    $columns[] = $this->identityColumn($resolved, ['label' => 'Title']);
                    continue;
                }

                $columns[] = $this->fieldBackedColumn($resolved->field($config));

                continue;
            }

            if (! is_array($config) || ! isset($config['type'])) {
                continue;
            }

            $config = $this->normalizeColumnFieldConfig($resolved, $config);

            $columns[] = match ($config['type']) {
                'id' => Id::make(),
                'identity' => $this->identityColumn($resolved, $config),
                'text' => $this->textColumn($config),
                'number' => $this->numberColumn($config),
                'badge' => $this->badgeColumn($config),
                'ago' => $this->agoColumn($config),
                default => throw new RuntimeException("Unsupported table column type [{$config['type']}]."),
            };
        }

        return $columns;
    }

    private function typedColumn(ResolvedViewDefinition $resolved, TableColumnDefinition $column): DisplayField
    {
        return match ($column->type()) {
            'id' => Id::make(),
            'identity' => $this->identityColumn($resolved, [
                'label' => $column->getLabel(),
                'sortAs' => $column->getSortAs(),
                'modal' => $column->isModal(),
            ]),
            'text' => $this->textColumn([
                'field' => $column->getField(),
                'label' => $column->getLabel(),
                'sortAs' => $column->getSortAs(),
                'suffix' => $column->getSuffix(),
                'help' => $column->getHelp(),
                'modal' => $column->isModal(),
                'title' => $column->isTitle(),
            ]),
            'number' => $this->numberColumn([
                'field' => $column->getField(),
                'label' => $column->getLabel(),
                'sortAs' => $column->getSortAs(),
                'suffix' => $column->getSuffix(),
                'help' => $column->getHelp(),
                'modal' => $column->isModal(),
            ]),
            'badge' => $this->badgeColumn([
                'field' => $column->getField(),
                'label' => $column->getLabel(),
                'help' => $column->getHelp(),
                'modal' => $column->isModal() ? ($column->getField() ?? '') : null,
                'source' => $column->getSource(),
            ]),
            'ago' => $this->agoColumn([
                'field' => $column->getField(),
                'label' => $column->getLabel(),
                'sortAs' => $column->getSortAs(),
            ]),
            'date' => $this->dateColumn([
                'field' => $column->getField(),
                'label' => $column->getLabel(),
                'sortAs' => $column->getSortAs(),
                'modal' => $column->isModal(),
            ]),
            default => throw new RuntimeException("Unsupported typed table column [{$column->type()}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function identityColumn(ResolvedViewDefinition $resolved, array $config): Text
    {
        $identity = $resolved->identity();
        $titleField = $identity?->getTitle();

        if (! is_string($titleField) || $titleField === '') {
            throw new RuntimeException('Identity column requires a string title field.');
        }

        $titleDefinition = null;

        try {
            $titleDefinition = $resolved->field($titleField);
        } catch (\Throwable) {
            $titleDefinition = null;
        }

        $titleFieldKey = $titleField;

        try {
            $titleFieldKey = $resolved->fieldKey($titleField);
        } catch (\Throwable) {
            $titleFieldKey = $titleField;
        }

        $column = Text::make($titleFieldKey)
            ->label((string) ($config['label'] ?? $titleDefinition?->getLabel() ?? 'Title'))
            ->title();

        if (is_string($config['sortAs'] ?? null)) {
            $column->sortAs($config['sortAs']);
        } elseif ($titleDefinition instanceof FieldDefinition && is_string($titleDefinition->getMeta('sortAs')) && $titleDefinition->getMeta('sortAs') !== '') {
            $column->sortAs($titleDefinition->getMeta('sortAs'));
        }

        if ((bool) ($config['modal'] ?? false) || ($titleDefinition instanceof FieldDefinition && (bool) $titleDefinition->getMeta('modal', false))) {
            $column->modal();
        }

        $imageDefinition = $identity?->getImageDefinition();

        if ($imageDefinition instanceof IdentityImageDefinition && is_string($imageDefinition->getField()) && $imageDefinition->getField() !== '') {
            $imageFieldKey = $imageDefinition->getField();

            try {
                $imageFieldKey = $resolved->fieldKey($imageFieldKey);
            } catch (\Throwable) {
                $imageFieldKey = (string) $imageDefinition->getField();
            }

            $column
                ->image($imageFieldKey)
                ->imageMediaUuid(is_string($imageDefinition->getMediaUuid()) ? $imageDefinition->getMediaUuid() : null)
                ->imageMediaVersion(is_string($imageDefinition->getMediaVersion()) ? $imageDefinition->getMediaVersion() : null)
                ->imageFocusPoint(is_string($imageDefinition->getFocusPoint()) ? $imageDefinition->getFocusPoint() : null)
                ->imagePreset($imageDefinition->getPreset())
                ->imageWidths($imageDefinition->getWidths())
                ->imageSizes($imageDefinition->getSizes())
                ->imageSquare($imageDefinition->isSquare())
                ->imageInitialsFallback($imageDefinition->useInitialsFallback());
        } else {
            $imageField = $identity?->getImage();

            if (is_string($imageField) && $imageField !== '') {
                $imageFieldKey = $imageField;

                try {
                    $imageFieldKey = $resolved->fieldKey($imageField);
                } catch (\Throwable) {
                    $imageFieldKey = $imageField;
                }

                $column
                    ->image($imageFieldKey)
                    ->imageMediaUuid($identity->getMeta('image_media_uuid_field'))
                    ->imageMediaVersion($identity->getMeta('image_media_version_field'))
                    ->imageFocusPoint($identity->getMeta('image_focus_point_field'))
                    ->imagePreset($identity->getMeta('image_preset'))
                    ->imageWidths(is_array($identity->getMeta('image_widths')) ? $identity->getMeta('image_widths') : [])
                    ->imageSizes(is_string($identity->getMeta('image_sizes')) ? $identity->getMeta('image_sizes') : null)
                    ->imageSquare((bool) $identity->getMeta('image_square', false))
                    ->imageInitialsFallback((bool) $identity->getMeta('image_initials_fallback', true));
            }
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeColumnFieldConfig(ResolvedViewDefinition $resolved, array $config): array
    {
        $fieldReference = $config['field'] ?? null;

        if (! is_string($fieldReference) || $fieldReference === '' || ! str_contains($fieldReference, '.')) {
            return $config;
        }

        try {
            $definition = $resolved->field($fieldReference);
        } catch (\Throwable) {
            return $config;
        }

        $config['field'] = $definition->name();
        $config['label'] ??= $definition->getLabel() ?? ucfirst($definition->name());

        if (! isset($config['sortAs']) && is_string($definition->getSortAs()) && $definition->getSortAs() !== '') {
            $config['sortAs'] = $definition->getSortAs();
        } elseif (! isset($config['sortAs']) && is_string($definition->getMeta('sortAs')) && $definition->getMeta('sortAs') !== '') {
            $config['sortAs'] = $definition->getMeta('sortAs');
        }

        if (! isset($config['suffix']) && is_string($definition->getSuffix()) && $definition->getSuffix() !== '') {
            $config['suffix'] = $definition->getSuffix();
        } elseif (! isset($config['suffix']) && is_string($definition->getMeta('suffix')) && $definition->getMeta('suffix') !== '') {
            $config['suffix'] = $definition->getMeta('suffix');
        }

        if (! isset($config['help']) && is_string($definition->getHelp()) && $definition->getHelp() !== '') {
            $config['help'] = $definition->getHelp();
        }

        if (! isset($config['modal']) && $definition->isModal()) {
            $config['modal'] = true;
        } elseif (! isset($config['modal']) && (bool) $definition->getMeta('modal', false)) {
            $config['modal'] = true;
        }

        if (! isset($config['title']) && $definition->isTitle()) {
            $config['title'] = true;
        } elseif (! isset($config['title']) && (bool) $definition->getMeta('title', false)) {
            $config['title'] = true;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function textColumn(array $config): Text
    {
        $field = (string) ($config['field'] ?? '');

        if ($field === '') {
            throw new RuntimeException('Text column requires a field name.');
        }

        $column = Text::make($field)->label((string) ($config['label'] ?? ucfirst($field)));

        if (is_string($config['sortAs'] ?? null)) {
            $column->sortAs($config['sortAs']);
        }

        if (is_string($config['suffix'] ?? null) && $config['suffix'] !== '') {
            $column->suffix($config['suffix']);
        }

        if (is_string($config['help'] ?? null) && $config['help'] !== '') {
            $column->help($config['help']);
        }

        if ((bool) ($config['modal'] ?? false)) {
            $column->modal();
        }

        if ((bool) ($config['title'] ?? false)) {
            $column->title();
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function numberColumn(array $config): DisplayNumber
    {
        $field = (string) ($config['field'] ?? '');

        if ($field === '') {
            throw new RuntimeException('Number column requires a field name.');
        }

        $column = DisplayNumber::make($field)->label((string) ($config['label'] ?? ucfirst($field)));

        if (is_string($config['sortAs'] ?? null)) {
            $column->sortAs($config['sortAs']);
        }

        if (is_string($config['suffix'] ?? null) && $config['suffix'] !== '') {
            $column->suffix($config['suffix']);
        }

        if (is_string($config['help'] ?? null) && $config['help'] !== '') {
            $column->help($config['help']);
        }

        if ((bool) ($config['modal'] ?? false)) {
            $column->modal();
        }

        return $column;
    }

    private function fieldBackedColumn(FieldDefinition $field): DisplayField
    {
        return match ($field->getListType()) {
            'ago' => $this->agoColumn([
                'field' => $field->name(),
                'label' => $field->getLabel() ?? ucfirst($field->name()),
                'sortAs' => $field->getSortAs() ?? $field->getMeta('sortAs'),
            ]),
            'number' => $this->numberColumn([
                'field' => $field->name(),
                'label' => $field->getLabel() ?? ucfirst($field->name()),
                'sortAs' => $field->getSortAs() ?? $field->getMeta('sortAs'),
                'modal' => $field->isModal() || $field->getMeta('modal'),
                'suffix' => $field->getSuffix() ?? $field->getMeta('suffix'),
                'help' => $field->getHelp(),
            ]),
            'date' => $this->dateColumn([
                'field' => $field->name(),
                'label' => $field->getLabel() ?? ucfirst($field->name()),
                'sortAs' => $field->getSortAs() ?? $field->getMeta('sortAs'),
                'modal' => $field->isModal() || $field->getMeta('modal'),
            ]),
            default => $this->textColumnFromDefinition($field),
        };
    }

    private function textColumnFromDefinition(FieldDefinition $field): Text
    {
        $column = Text::make($field->name())
            ->label($field->getLabel() ?? ucfirst($field->name()));

        if (is_string($field->getSortAs()) && $field->getSortAs() !== '') {
            $column->sortAs($field->getSortAs());
        } elseif (is_string($field->getMeta('sortAs')) && $field->getMeta('sortAs') !== '') {
            $column->sortAs($field->getMeta('sortAs'));
        }

        if ($field->isTitle() || (bool) $field->getMeta('title', false)) {
            $column->title();
        }

        if ($field->isModal() || (bool) $field->getMeta('modal', false)) {
            $column->modal();
        }

        if (is_string($field->getSuffix()) && $field->getSuffix() !== '') {
            $column->suffix($field->getSuffix());
        } elseif (is_string($field->getMeta('suffix')) && $field->getMeta('suffix') !== '') {
            $column->suffix($field->getMeta('suffix'));
        }

        if ($field->getHelp() !== null) {
            $column->help($field->getHelp());
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dateColumn(array $config): DisplayDate
    {
        $field = (string) ($config['field'] ?? '');

        if ($field === '') {
            throw new RuntimeException('Date column requires a field name.');
        }

        $column = DisplayDate::make($field)->label((string) ($config['label'] ?? ucfirst($field)));

        if (is_string($config['sortAs'] ?? null)) {
            $column->sortAs($config['sortAs']);
        }

        if ((bool) ($config['modal'] ?? false)) {
            $column->modal();
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function agoColumn(array $config): Ago
    {
        $field = (string) ($config['field'] ?? '');

        if ($field === '') {
            throw new RuntimeException('Ago column requires a field name.');
        }

        $column = Ago::make($field)->label((string) ($config['label'] ?? ucfirst($field)));

        if (is_string($config['sortAs'] ?? null)) {
            $column->sortAs($config['sortAs']);
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function badgeColumn(array $config): Badge
    {
        $field = (string) ($config['field'] ?? '');

        if ($field === '') {
            throw new RuntimeException('Badge column requires a field name.');
        }

        $column = Badge::make($field)->label((string) ($config['label'] ?? ucfirst($field)));

        if (isset($config['source']) && $config['source'] instanceof \Closure) {
            $column->source($config['source']);
        }

        if (is_string($config['modal'] ?? null) && $config['modal'] !== '') {
            $column->modal($config['modal']);
        }

        if (is_string($config['help'] ?? null) && $config['help'] !== '') {
            $column->help($config['help']);
        }

        return $column;
    }
}
