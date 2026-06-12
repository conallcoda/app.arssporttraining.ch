<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Display\Table;
use Coda\Cms\Layout\Layout;
use Coda\FormKit\Form;
use Coda\SchemaKit\SchemaRegistry;

class CmsSchemaPresenter
{
    public static function make(
        SchemaRegistry $registry,
        ?SchemaFormFieldFactory $fieldFactory = null,
    ): static {
        $fieldFactory ??= app(SchemaFormFieldFactory::class);

        return new static(
            $registry,
            new SchemaFormAdapter($registry, $fieldFactory),
            new SchemaDetailsAdapter($registry),
            new SchemaTableAdapter($registry),
        );
    }

    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly SchemaFormAdapter $forms,
        private readonly SchemaDetailsAdapter $details,
        private readonly SchemaTableAdapter $tables,
    ) {}

    public function form(string $entityName, string $viewName): Form
    {
        return $this->forms->form($entityName, $viewName);
    }

    public function entityLabel(string $entityName): string
    {
        return $this->registry->entity($entityName)->getLabel()
            ?? ucfirst(str_replace('_', ' ', $entityName));
    }

    public function pluralEntityLabel(string $entityName): string
    {
        return $this->registry->entity($entityName)->getPluralLabel()
            ?? $this->entityLabel($entityName).'s';
    }

    public function formModalMaxWidth(string $entityName, string $viewName, string $default = 'max-w-sm'): string
    {
        $view = $this->registry->resolveView($entityName, $viewName)->view();
        $value = $view->getFormModalWidth() ?? $view->getMeta('form_modal_max_width');

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function details(string $entityName, string $viewName): Layout
    {
        return $this->details->details($entityName, $viewName);
    }

    public function table(string $entityName, string $viewName): Table
    {
        return $this->tables->table($entityName, $viewName);
    }
}
