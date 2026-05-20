<?php

namespace Coda\SchemaKit;

use DateTimeInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use Illuminate\Support\Arr;
use Coda\SchemaKit\Attributes\AppliesToFieldDefinition;
use Coda\SchemaKit\Attributes\CreatesFieldDefinition;
use Coda\SchemaKit\Attributes\ProvidesValidationRules;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Rule as GenericValidationRule;
use Spatie\LaravelData\Attributes\Validation\ObjectValidationAttribute;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringValidationAttribute;
use Spatie\LaravelData\Support\Validation\ValidationPath;

class FacetDefinition
{
    private ?string $owningModelClass = null;

    private ?string $label = null;

    private ?string $description = null;

    private array $fields = [];

    /** @var array<string, FieldDefinition> */
    private array $fieldDefinitions = [];

    private ?string $dataClass = null;

    private ?string $dataPath = null;

    private bool $inferFields = true;

    private ?FacetFormDefinition $form = null;

    private ?FacetDetailsDefinition $details = null;

    private ?StorageStrategyData $storage = null;

    /** @var array<int, FacetApplicabilityRuleData> */
    private array $applicability = [];

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

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function field(string $field, ?callable $configure = null): FieldDefinition|static
    {
        if ($configure !== null) {
            return $this->defineField($field, $configure);
        }

        if (in_array($field, $this->fields, true)) {
            return $this;
        }

        $this->fields[] = $field;

        return $this;
    }

    public function fields(array $fields): static
    {
        foreach ($fields as $field) {
            if ($field instanceof FieldDefinition) {
                $this->defineField($field);

                continue;
            }

            $this->field($field);
        }

        return $this;
    }

    public function relationships(array $relationships): static
    {
        foreach ($relationships as $relationship) {
            $this->defineRelationship($relationship);
        }

        return $this;
    }

    public function computed(array $computed): static
    {
        foreach ($computed as $definition) {
            $this->defineComputed($definition);
        }

        return $this;
    }

    public function defineField(FieldDefinition|string $field, ?callable $configure = null): FieldDefinition|static
    {
        if ($field instanceof FieldDefinition) {
            $name = $field->name();
            $this->field($name);
            $this->fieldDefinitions[$name] = $field;

            return $this;
        }

        $name = $field;
        $this->field($name);
        $hasExplicitDefinition = array_key_exists($name, $this->fieldDefinitions);
        $field = $this->fieldDefinitions[$name] ?? $this->inferredFieldDefinitions()[$name] ?? null;

        if ($field === null) {
            $field = $this->fieldDefinitions[$name] = new Field($name);

            if ($configure === null) {
                return $field;
            }

            $configure($field);

            return $this;
        }

        if (! $hasExplicitDefinition) {
            $field = $this->fieldDefinitions[$name] = clone $field;
        }

        if ($configure === null) {
            return $field;
        }

        if ($hasExplicitDefinition) {
            if ((bool) $field->getMeta('_allow_local_override', false) === true) {
                $configure($field);
            }

            return $this;
        }

        $configure($field);

        return $this;
    }

    public function defineRelationship(RelationshipDefinition|string $field, ?callable $configure = null): RelationshipDefinition|static
    {
        if ($field instanceof RelationshipDefinition) {
            $this->defineField($field);

            return $this;
        }

        $hasExplicitDefinition = array_key_exists($field, $this->fieldDefinitions);
        $definition = $this->fieldDefinitions[$field] ?? $this->inferredFieldDefinitions()[$field] ?? Relationship::make($field);

        if (! $definition instanceof RelationshipDefinition) {
            $definition = Relationship::make($field)
                ->label($definition->getLabel() ?? $this->humanize($field))
                ->help($definition->getHelp() ?? '')
                ->input($definition->getInput())
                ->placeholder($definition->getPlaceholder())
                ->required($definition->isRequired())
                ->rules($definition->getRules())
                ->readable($definition->isReadable())
                ->writable($definition->isWritable())
                ->formVisible($definition->isFormVisible())
                ->sortAs($definition->getSortAs())
                ->suffix($definition->getSuffix())
                ->title($definition->isTitle())
                ->modal($definition->isModal())
                ->readUsing($definition->getReadUsing())
                ->writeUsing($definition->getWriteUsing())
                ->attribute($definition->getAttribute());
        }

        $this->field($field);
        $this->fieldDefinitions[$field] = $definition;

        if ($configure === null) {
            return $definition;
        }

        if ($hasExplicitDefinition) {
            if ((bool) $definition->getMeta('_allow_local_override', false) === true) {
                $configure($definition);
            }

            return $this;
        }

        $configure($definition);

        return $this;
    }

    public function defineComputed(ComputedDefinition|string $field, ?callable $configure = null): ComputedDefinition|static
    {
        if ($field instanceof ComputedDefinition) {
            $this->defineField($field);

            return $this;
        }

        $hasExplicitDefinition = array_key_exists($field, $this->fieldDefinitions);
        $definition = $this->fieldDefinitions[$field] ?? $this->inferredFieldDefinitions()[$field] ?? Computed::make($field);

        if (! $definition instanceof ComputedDefinition) {
            $definition = Computed::make($field)
                ->label($definition->getLabel() ?? $this->humanize($field))
                ->help($definition->getHelp() ?? '')
                ->input($definition->getInput())
                ->placeholder($definition->getPlaceholder())
                ->required($definition->isRequired())
                ->rules($definition->getRules())
                ->readable($definition->isReadable())
                ->writable(false)
                ->formVisible($definition->isFormVisible())
                ->sortAs($definition->getSortAs())
                ->suffix($definition->getSuffix())
                ->title($definition->isTitle())
                ->modal($definition->isModal())
                ->readUsing($definition->getReadUsing())
                ->writeUsing($definition->getWriteUsing())
                ->attribute($definition->getAttribute());
        }

        $this->field($field);
        $this->fieldDefinitions[$field] = $definition;

        if ($configure === null) {
            return $definition;
        }

        if ($hasExplicitDefinition) {
            if ((bool) $definition->getMeta('_allow_local_override', false) === true) {
                $configure($definition);
            }

            return $this;
        }

        $configure($definition);

        return $this;
    }

    public function fieldLabel(string $field, string $label): static
    {
        $this->defineField($field, static fn (FieldDefinition $definition) => $definition->label($label));

        return $this;
    }

    public function fieldHelp(string $field, string $help): static
    {
        $this->defineField($field, static fn (FieldDefinition $definition) => $definition->help($help));

        return $this;
    }

    public function hideField(string $field, bool $readable = false): static
    {
        $this->defineField($field, static fn (FieldDefinition $definition) => $definition
            ->formVisible(false)
            ->readable($readable));

        return $this;
    }

    public function hideFields(array $fields, bool $readable = false): static
    {
        foreach ($fields as $field) {
            $this->hideField($field, $readable);
        }

        return $this;
    }

    public function computedText(string $field, ?string $label = null, ?string $attribute = null, bool $modal = false): static
    {
        $definition = Computed::make($field)
            ->label($label ?? $this->humanize($field))
            ->computedUsing($field)
            ->listType('text')
            ->formVisible(false);

        if ($attribute !== null) {
            $definition->attribute($attribute);
        }

        if ($modal) {
            $definition->modal();
        }

        $this->defineComputed($definition);

        return $this;
    }

    public function computedHidden(string $field, ?string $label = null, ?string $attribute = null): static
    {
        $definition = Computed::make($field)
            ->label($label ?? $this->humanize($field))
            ->computedUsing($field)
            ->formVisible(false);

        if ($attribute !== null) {
            $definition->attribute($attribute);
        }

        $this->defineComputed($definition);

        return $this;
    }

    public function computedAgo(string $field, ?string $label = null, ?string $attribute = null): static
    {
        $definition = Computed::make($field)
            ->label($label ?? $this->humanize($field))
            ->computedUsing($field)
            ->listType('ago')
            ->formVisible(false);

        if ($attribute !== null) {
            $definition->attribute($attribute);
        }

        $this->defineComputed($definition);

        return $this;
    }

    public function form(FacetFormDefinition|callable|null $configure = null): FacetFormDefinition|static
    {
        if ($configure instanceof FacetFormDefinition) {
            $this->form = $configure;

            return $this;
        }

            $this->form ??= new FacetFormDefinition;

        if ($configure === null) {
            return $this->form;
        }

        $configure($this->form);

        return $this;
    }

    public function details(FacetDetailsDefinition|callable|null $configure = null): FacetDetailsDefinition|static
    {
        if ($configure instanceof FacetDetailsDefinition) {
            $this->details = $configure;

            return $this;
        }

            $this->details ??= new FacetDetailsDefinition;

        if ($configure === null) {
            return $this->details;
        }

        $configure($this->details);

        return $this;
    }

    public function section(
        string $tab,
        ?string $label = null,
        ?string $view = 'form.section-fieldset',
        array $viewData = [],
        ?string $fieldset = null,
    ): static {
        $label ??= $this->getLabel() ?? $this->humanize($this->name);

        $this->form(function (FacetFormDefinition $form) use ($label, $view, $viewData, $tab): void {
            $form->label($label)->tab($tab);

            if ($view !== null) {
                $form->view($view);
            }

            if ($viewData !== []) {
                $form->viewData($viewData);
            }
        });

        $this->details(fn (FacetDetailsDefinition $details) => $details->fieldset($fieldset ?? $this->name));

        return $this;
    }

    public function dataClass(?string $dataClass): static
    {
        $this->dataClass = $dataClass;

        return $this;
    }

    public function data(?string $dataClass, ?string $dataPath = null): static
    {
        $this->dataClass($dataClass);

        if ($dataPath !== null) {
            $this->dataPath($dataPath);
        }

        return $this;
    }

    public function relationship(string|RelationshipDefinition $field, ?callable $configure = null): RelationshipDefinition|static
    {
        return $this->defineRelationship($field, $configure);
    }

    public function owningModelClass(?string $owningModelClass): static
    {
        $this->owningModelClass = $owningModelClass;

        return $this;
    }

    public function dataPath(?string $dataPath): static
    {
        $this->dataPath = $dataPath;

        return $this;
    }

    public function inferFields(bool $inferFields = true): static
    {
        $this->inferFields = $inferFields;

        return $this;
    }

    public function storage(?StorageStrategyData $storage): static
    {
        $this->storage = $storage;

        return $this;
    }

    public function applicability(FacetApplicabilityRuleData ...$rules): static
    {
        $this->applicability = array_values($rules);

        return $this;
    }

    public function applicableWhen(
        ?string $schemaKey = null,
        ?string $scopeType = null,
        int|string|null $scopeId = null,
        ?string $taxonomyType = null,
        int|string|null $taxonomyTerm = null,
        int $priority = 0,
        string $mode = 'include',
    ): static {
        $this->applicability[] = new FacetApplicabilityRuleData(
            schemaKey: $schemaKey,
            scopeType: $scopeType,
            scopeId: $scopeId,
            taxonomyType: $taxonomyType,
            taxonomyTerm: $taxonomyTerm,
            priority: $priority,
            mode: $mode,
        );

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getForm(): ?FacetFormDefinition
    {
        return $this->form;
    }

    public function getDetails(): ?FacetDetailsDefinition
    {
        return $this->details;
    }

    public function getFields(): array
    {
        return array_values(array_unique([
            ...array_keys($this->inferredFieldDefinitions()),
            ...array_keys($this->schemaFieldDefinitions()),
            ...$this->fields,
        ]));
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public function getFieldDefinitions(): array
    {
        return [
            ...$this->inferredFieldDefinitions(),
            ...$this->schemaFieldDefinitions(),
            ...$this->fieldDefinitions,
        ];
    }

    public function getDataClass(): ?string
    {
        return $this->dataClass;
    }

    public function getDataPath(): ?string
    {
        return $this->dataPath;
    }

    public function shouldInferFields(): bool
    {
        return $this->inferFields;
    }

    public function getStorage(): ?StorageStrategyData
    {
        return $this->storage;
    }

    /**
     * @return array<int, FacetApplicabilityRuleData>
     */
    public function getApplicability(): array
    {
        return $this->applicability;
    }

    public function toDefinitionData(): FacetDefinitionData
    {
        return new FacetDefinitionData(
            key: $this->name(),
            label: $this->getLabel(),
            description: $this->getDescription(),
            dataClass: $this->getDataClass(),
            dataPath: $this->getDataPath(),
            inferFields: $this->shouldInferFields(),
            storage: $this->getStorage(),
            fields: array_values(array_map(
                static fn (FieldDefinition $field): FieldDefinitionData => $field->toDefinitionData(),
                $this->getFieldDefinitions(),
            )),
            applicability: $this->getApplicability(),
            meta: $this->allMeta(),
        );
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function inferredFieldDefinitions(): array
    {
        if (! $this->inferFields || ! is_string($this->dataClass) || $this->dataClass === '' || ! class_exists($this->dataClass)) {
            return [];
        }

        $reflection = new ReflectionClass($this->dataClass);
        $constructor = $reflection->getConstructor();
        $definitions = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                $definitions[$name] = $this->inferFieldDefinition($name, $parameter->getType(), $parameter);
            }

            return $definitions;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $definitions[$property->getName()] = $this->inferFieldDefinition($property->getName(), $property->getType(), $property);
        }

        return $definitions;
    }

    private function inferFieldDefinition(string $name, ?ReflectionType $type, ReflectionParameter|ReflectionProperty|null $member = null): FieldDefinition
    {
        $field = $this->fieldDefinitionFromAttributes($name, $member);

        if ($field === null) {
            $field = $this->inferRelationshipDefinition($name);
        }

        if ($field === null) {
            $field = Field::make($name);
        }

        $field->label($field->getLabel() ?? $this->humanize($name));

        $relationship = $this->inferRelationshipDefinition($name);

        if ($field instanceof RelationshipDefinition && $relationship !== null) {
            $field
                ->label($field->getLabel() ?? $relationship->getLabel() ?? $this->humanize($name))
                ->relationshipType($field->getRelationshipType() ?? $relationship->getRelationshipType())
                ->multiple($field->isMultiple() || $relationship->isMultiple())
                ->to($field->getTargetEntity() ?? $relationship->getTargetEntity());
        }

        $validationMetadata = $this->validationMetadataForMember($member);

        if ($validationMetadata['required']) {
            $field->required();
        }

        if ($validationMetadata['rules'] !== []) {
            $field->rules($validationMetadata['rules']);
        }

        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $isObjectType = $type instanceof ReflectionNamedType && ! $type->isBuiltin();
        $isDateType = ($typeName !== null && is_a($typeName, DateTimeInterface::class, true))
            || in_array($name, ['birthdate', 'startsAt', 'endsAt'], true);

        if ($field->getRules() === null && $typeName === 'string' && preg_match('/(^email$|Email$)/', $name) === 1) {
            $field->rules('nullable|email');
        }

        if (preg_match('/(Url|Website)$/', $name) === 1 || in_array($name, ['twitter', 'linkedin', 'website'], true)) {
            $field->input(UrlInput::make()->placeholder('https://'));
        } elseif ($isDateType) {
            $field->input(DateInput::make());
        }

        $this->applySchemaAttributes($field, $member);

        if ($isObjectType) {
            return $field;
        }

        return $field;
    }

    private function fieldDefinitionFromAttributes(string $name, ReflectionParameter|ReflectionProperty|null $member): ?FieldDefinition
    {
        if ($member === null) {
            return null;
        }

        foreach ($member->getAttributes() as $attribute) {
            try {
                $instance = $attribute->newInstance();
            } catch (\Error) {
                continue;
            }

            if ($instance instanceof CreatesFieldDefinition) {
                return $instance->createFieldDefinition($name);
            }
        }

        return null;
    }

    /**
     * @return array{required: bool, rules: array<int, string|object>}
     */
    private function validationMetadataForMember(ReflectionParameter|ReflectionProperty|null $member): array
    {
        if ($member === null) {
            return ['required' => false, 'rules' => []];
        }

        $rules = [];
        $required = false;
        $nullable = $member->getType()?->allowsNull() ?? false;

        foreach ($member->getAttributes(Required::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();
            $required = $required || $instance instanceof Required;
        }

        foreach ($member->getAttributes() as $attribute) {
            try {
                $instance = $attribute->newInstance();
            } catch (\Error) {
                continue;
            }

            if (! $instance instanceof ProvidesValidationRules) {
                continue;
            }

            $normalizedRules = $this->normalizeGenericValidationRules($instance->rules());

            if (in_array('required', $normalizedRules, true)) {
                $required = true;
            }

            array_push($rules, ...$normalizedRules);
        }

        foreach ($member->getAttributes(GenericValidationRule::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $normalizedRules = $this->normalizeGenericValidationRules($attribute->newInstance()->get());

            if (in_array('required', $normalizedRules, true)) {
                $required = true;
            }

            array_push($rules, ...$normalizedRules);
        }

        foreach ($member->getAttributes(StringValidationAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $rule = $this->normalizeValidationRule($attribute->newInstance());

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        foreach ($member->getAttributes(ObjectValidationAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof Required) {
                continue;
            }

            $rule = $this->normalizeValidationRule($instance);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        if ($nullable && ! $required && ! in_array('nullable', $rules, true)) {
            array_unshift($rules, 'nullable');
        }

        if ($required) {
            array_unshift($rules, Required::keyword());
        }

        $rules = array_values(array_reduce($rules, function (array $carry, string|object $rule): array {
            foreach ($carry as $existing) {
                if ($existing === $rule) {
                    return $carry;
                }
            }

            $carry[] = $rule;

            return $carry;
        }, []));

        return [
            'required' => $required,
            'rules' => $rules,
        ];
    }

    /**
     * @return array<int, string|object>
     */
    private function normalizeGenericValidationRules(mixed $rules): array
    {
        if (is_array($rules)) {
            return Arr::flatten(array_map(
                fn (mixed $rule): array => $this->normalizeGenericValidationRules($rule),
                $rules,
            ));
        }

        if (is_string($rules)) {
            return str_contains($rules, 'regex:')
                ? [$rules]
                : explode('|', $rules);
        }

        return [$rules];
    }

    private function normalizeValidationRule(object $attribute): string|object|null
    {
        if ($attribute instanceof ObjectValidationAttribute) {
            return $attribute->getRule(ValidationPath::create());
        }

        if (! $attribute instanceof StringValidationAttribute) {
            return null;
        }

        $parameters = array_map(
            static fn (mixed $parameter): string => is_bool($parameter)
                ? ($parameter ? 'true' : 'false')
                : (string) $parameter,
            Arr::flatten($attribute->parameters()),
        );

        return $parameters === []
            ? $attribute::keyword()
            : $attribute::keyword().':'.implode(',', $parameters);
    }

    private function applySchemaAttributes(FieldDefinition $field, ReflectionParameter|ReflectionProperty|null $member): void
    {
        if ($member === null) {
            return;
        }

        foreach ($member->getAttributes() as $attribute) {
            try {
                $instance = $attribute->newInstance();
            } catch (\Error) {
                continue;
            }

            if ($instance instanceof AppliesToFieldDefinition) {
                $instance->apply($field);

                continue;
            }

            if (! $instance instanceof MapInputName) {
                continue;
            }

            if (! is_string($instance->input) || $instance->input === '' || str_contains($instance->input, '.')) {
                continue;
            }

            $field->attribute($instance->input);
        }
    }

    private function inferRelationshipDefinition(string $name): ?RelationshipDefinition
    {
        $metadata = $this->relationMetadataForProperty($name);

        if ($metadata === null) {
            return null;
        }

        return RelationshipDefinition::make($name)
            ->label($this->humanize($metadata['label'] ?? $name))
            ->relationshipType($metadata['relationshipType'])
            ->multiple($metadata['multiple']);
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function schemaFieldDefinitions(): array
    {
        if (! is_string($this->dataClass) || $this->dataClass === '' || ! is_subclass_of($this->dataClass, DefinesSchema::class)) {
            return [];
        }

        $schema = $this->dataClass::schema();
        $definitions = [];

        foreach (($schema['fields'] ?? []) as $field) {
            $definition = $field instanceof FieldDefinition ? $field : Field::make($field);
            $definitions[$definition->name()] = $definition;
        }

        foreach (($schema['relationships'] ?? []) as $relationship) {
            $definition = $relationship instanceof RelationshipDefinition ? $relationship : Relationship::make($relationship);
            $definitions[$definition->name()] = $definition;
        }

        foreach (($schema['computed'] ?? []) as $computed) {
            $definition = $computed instanceof ComputedDefinition ? $computed : Computed::make($computed);
            $definitions[$definition->name()] = $definition;
        }

        return $definitions;
    }

    /**
     * @return array{label?: string, relationshipType: ?string, multiple: bool}|null
     */
    private function relationMetadataForProperty(string $property): ?array
    {
        $relations = $this->modelRelationMetadata();
        $exactName = $this->normalizePropertyName($property);

        foreach ($relations as $name => $metadata) {
            if ($name === $exactName) {
                return $metadata;
            }
        }

        foreach ($relations as $name => $metadata) {
            $foreignKey = $metadata['foreignKey'] ?? null;

            if (is_string($foreignKey) && $this->normalizePropertyName($foreignKey) === $exactName) {
                return [
                    ...$metadata,
                    'label' => $name,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, array{foreignKey?: string|null, relationshipType: ?string, multiple: bool}>
     */
    private function modelRelationMetadata(): array
    {
        if (
            ! is_string($this->owningModelClass)
            || $this->owningModelClass === ''
            || ! class_exists($this->owningModelClass)
            || ! is_subclass_of($this->owningModelClass, \Illuminate\Database\Eloquent\Model::class)
        ) {
            return [];
        }

        static $cache = [];

        if (array_key_exists($this->owningModelClass, $cache)) {
            return $cache[$this->owningModelClass];
        }

        $model = new $this->owningModelClass;
        $reflection = new \ReflectionClass($this->owningModelClass);
        $relations = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $declaringClass = $method->getDeclaringClass()->getName();

            if ($declaringClass === \Illuminate\Database\Eloquent\Model::class) {
                continue;
            }

            $relationClass = $this->declaredRelationClass($method);

            try {
                $result = $method->invoke($model);
            } catch (\Throwable) {
                if ($relationClass === null) {
                    continue;
                }

                $relations[$method->getName()] = [
                    'foreignKey' => $this->guessedForeignKey($method->getName(), $relationClass),
                    'relationshipType' => $this->relationTypeFromClass($relationClass),
                    'multiple' => $this->relationIsMultipleFromClass($relationClass),
                ];

                continue;
            }

            if (! $result instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                continue;
            }

            $relations[$method->getName()] = [
                'foreignKey' => $this->relationForeignKey($result),
                'relationshipType' => $this->relationType($result),
                'multiple' => $this->relationIsMultiple($result),
            ];
        }

        return $cache[$this->owningModelClass] = $relations;
    }

    private function declaredRelationClass(\ReflectionMethod $method): ?string
    {
        $type = $method->getReturnType();

        if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        return is_a($name, \Illuminate\Database\Eloquent\Relations\Relation::class, true) ? $name : null;
    }

    private function relationForeignKey(\Illuminate\Database\Eloquent\Relations\Relation $relation): ?string
    {
        if (method_exists($relation, 'getForeignKeyName')) {
            return $relation->getForeignKeyName();
        }

        if (method_exists($relation, 'getForeignPivotKeyName')) {
            return $relation->getForeignPivotKeyName();
        }

        return null;
    }

    private function relationType(\Illuminate\Database\Eloquent\Relations\Relation $relation): ?string
    {
        return $this->relationTypeFromClass($relation::class);
    }

    private function relationIsMultiple(\Illuminate\Database\Eloquent\Relations\Relation $relation): bool
    {
        return $this->relationIsMultipleFromClass($relation::class);
    }

    private function relationTypeFromClass(string $relationClass): ?string
    {
        return match (true) {
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\BelongsTo::class, true) => 'belongs_to',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\BelongsToMany::class, true) => 'belongs_to_many',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\HasOne::class, true) => 'has_one',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\HasMany::class, true) => 'has_many',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\MorphToMany::class, true) => 'morph_to_many',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\MorphMany::class, true) => 'morph_many',
            is_a($relationClass, \Illuminate\Database\Eloquent\Relations\MorphOne::class, true) => 'morph_one',
            default => null,
        };
    }

    private function relationIsMultipleFromClass(string $relationClass): bool
    {
        return is_a($relationClass, \Illuminate\Database\Eloquent\Relations\BelongsToMany::class, true)
            || is_a($relationClass, \Illuminate\Database\Eloquent\Relations\HasMany::class, true)
            || is_a($relationClass, \Illuminate\Database\Eloquent\Relations\MorphMany::class, true)
            || is_a($relationClass, \Illuminate\Database\Eloquent\Relations\MorphToMany::class, true);
    }

    private function guessedForeignKey(string $relationName, string $relationClass): ?string
    {
        if (! is_a($relationClass, \Illuminate\Database\Eloquent\Relations\BelongsTo::class, true)) {
            return null;
        }

        $snake = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $relationName) ?? $relationName);

        return $snake.'_id';
    }

    private function normalizePropertyName(string $value): string
    {
        $value = str_replace('_', ' ', trim($value));
        $value = preg_replace('/(?<!^)([A-Z])/', ' $1', $value) ?? $value;

        return lcfirst(str_replace(' ', '', ucwords(strtolower($value))));
    }

    private function humanize(string $value): string
    {
        $value = preg_replace('/(?<!^)([A-Z])/', ' $1', $value) ?? $value;
        $value = str_replace([' Id', ' Url'], [' ID', ' URL'], $value);

        return ucwords(str_replace('_', ' ', $value));
    }
}
