<?php

namespace Coda\SchemaKit;

use Closure;

class FieldDefinition
{
    private ?InputDefinition $input = null;

    private ?StorageStrategyData $storage = null;

    private ?string $attribute = null;

    private ?string $label = null;

    private ?string $help = null;

    private string $formType = 'text';

    private string $listType = 'text';

    private ?string $placeholder = null;

    private bool $required = false;

    private string|array|Closure|null $rules = null;

    private bool $readable = true;

    private bool $writable = true;

    private bool $formVisible = true;

    private Closure|string|null $readUsing = null;

    private ?Closure $writeUsing = null;

    private ?string $sortAs = null;

    private ?string $suffix = null;

    private bool $title = false;

    private bool $modal = false;

    private QueryStrategy $queryStrategy = QueryStrategy::None;

    private array $meta = [];

    public function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function attribute(?string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function formType(string $formType): static
    {
        $this->formType = $formType;

        if ($this->input === null) {
            $this->input = match ($formType) {
                'textarea' => TextareaInput::make(),
                'url' => UrlInput::make(),
                'date' => DateInput::make(),
                'datetime' => DateTimeInput::make(),
                'select' => SelectInput::make(),
                'tree_select' => TreeSelectInput::make(),
                'tree' => TreeInput::make(),
                'repeater' => RepeaterInput::make(),
                'radio_segmented' => RadioSegmentedInput::make(),
                'image_upload' => ImageUploadInput::make(),
                'weighted_category_tree' => WeightedCategoryTreeInput::make(),
                default => TextInput::make(),
            };
        }

        return $this;
    }

    public function listType(string $listType): static
    {
        $this->listType = $listType;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        $this->input?->placeholder($placeholder);

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function rules(string|array|Closure|null $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function readable(bool $readable = true): static
    {
        $this->readable = $readable;

        return $this;
    }

    public function writable(bool $writable = true): static
    {
        $this->writable = $writable;

        return $this;
    }

    public function formVisible(bool $formVisible = true): static
    {
        $this->formVisible = $formVisible;

        return $this;
    }

    public function readUsing(Closure|string|null $readUsing): static
    {
        $this->readUsing = $readUsing;

        return $this;
    }

    public function writeUsing(?Closure $writeUsing): static
    {
        $this->writeUsing = $writeUsing;

        return $this;
    }

    public function input(?InputDefinition $input): static
    {
        $this->input = $input;

        if ($input !== null) {
            $this->formType = $input->kind();

            if ($input->getPlaceholder() !== null) {
                $this->placeholder = $input->getPlaceholder();
            }
        }

        return $this;
    }

    public function storage(?StorageStrategyData $storage): static
    {
        $this->storage = $storage;

        return $this;
    }

    public function queryStrategy(QueryStrategy|string $queryStrategy): static
    {
        $this->queryStrategy = is_string($queryStrategy)
            ? QueryStrategy::from($queryStrategy)
            : $queryStrategy;

        return $this;
    }

    public function sortAs(?string $sortAs): static
    {
        $this->sortAs = $sortAs;

        return $this;
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function title(bool $title = true): static
    {
        $this->title = $title;

        return $this;
    }

    public function modal(bool $modal = true): static
    {
        $this->modal = $modal;

        return $this;
    }

    public function setMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->meta;
    }

    public function getAttribute(): ?string
    {
        return $this->attribute;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function getFormType(): string
    {
        return $this->formType;
    }

    public function getListType(): string
    {
        return $this->listType;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getRules(): string|array|Closure|null
    {
        return $this->rules;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function isFormVisible(): bool
    {
        return $this->formVisible;
    }

    public function getReadUsing(): Closure|string|null
    {
        return $this->readUsing;
    }

    public function getWriteUsing(): ?Closure
    {
        return $this->writeUsing;
    }

    public function getInput(): ?InputDefinition
    {
        return $this->input;
    }

    public function getStorage(): ?StorageStrategyData
    {
        return $this->storage;
    }

    public function getQueryStrategy(): QueryStrategy
    {
        return $this->queryStrategy;
    }

    public function getSortAs(): ?string
    {
        return $this->sortAs;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function isTitle(): bool
    {
        return $this->title;
    }

    public function isModal(): bool
    {
        return $this->modal;
    }

    public function definitionType(): string
    {
        return 'field';
    }

    public function toDefinitionData(): FieldDefinitionData
    {
        $input = $this->getInput();
        $fieldTypeConfig = [];

        if ($input !== null) {
            $fieldTypeConfig = [
                'readonly' => $input->isReadonly(),
                'placeholder' => $input->getPlaceholder(),
                'default' => $input->getDefault(),
                'visible_when' => $input->getVisibleWhen(),
                'meta' => $input->allMeta(),
            ];

            if (method_exists($input, 'getOptions')) {
                $options = $input->getOptions();

                if (is_array($options)) {
                    $fieldTypeConfig['options'] = $options;
                }
            }

            if (method_exists($input, 'isMultiple')) {
                $fieldTypeConfig['multiple'] = $input->isMultiple();
            }
        }

        $fieldTypeConfig = array_filter(
            $fieldTypeConfig,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );

        return new FieldDefinitionData(
            key: $this->name(),
            definitionType: $this->definitionType(),
            label: $this->getLabel(),
            help: $this->getHelp(),
            attribute: $this->getAttribute(),
            required: $this->isRequired(),
            readable: $this->isReadable(),
            writable: $this->isWritable(),
            formVisible: $this->isFormVisible(),
            repeatable: $this instanceof RelationshipDefinition
                ? $this->isMultiple()
                : (($fieldTypeConfig['multiple'] ?? false) === true),
            fieldType: new FieldTypeData($input?->kind() ?? $this->getFormType(), $fieldTypeConfig),
            storage: $this->getStorage(),
            queryStrategy: $this->getQueryStrategy(),
            meta: $this->allMeta(),
        );
    }

    public function __clone()
    {
        if ($this->input !== null) {
            $this->input = clone $this->input;
        }
    }
}
