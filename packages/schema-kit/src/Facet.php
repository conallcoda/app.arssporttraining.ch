<?php

namespace Coda\SchemaKit;

/**
 * @method static static make(string $name)
 * @method static label(string $label)
 * @method static description(string $description)
 * @method static field(string $field, ?callable $configure = null)
 * @method static fields(array $fields)
 * @method static relationships(array $relationships)
 * @method static computed(array $computed)
 * @method static defineField(FieldDefinition|string $field, ?callable $configure = null)
 * @method static defineRelationship(RelationshipDefinition|string $field, ?callable $configure = null)
 * @method static defineComputed(ComputedDefinition|string $field, ?callable $configure = null)
 * @method static fieldLabel(string $field, string $label)
 * @method static fieldHelp(string $field, string $help)
 * @method static hideField(string $field, bool $readable = false)
 * @method static hideFields(array $fields, bool $readable = false)
 * @method static computedText(string $field, ?string $label = null, ?string $attribute = null, bool $modal = false)
 * @method static computedHidden(string $field, ?string $label = null, ?string $attribute = null)
 * @method static computedAgo(string $field, ?string $label = null, ?string $attribute = null)
 * @method static form(?callable $configure = null)
 * @method static details(?callable $configure = null)
 * @method static section(string $tab, ?string $label = null, ?string $view = 'form.section-fieldset', array $viewData = [], ?string $fieldset = null)
 * @method static dataClass(?string $dataClass)
 * @method static data(?string $dataClass, ?string $dataPath = null)
 * @method static dataPath(?string $dataPath)
 * @method static inferFields(bool $inferFields = true)
 * @method static relationship(string|RelationshipDefinition $field, ?callable $configure = null)
 * @method static owningModelClass(?string $owningModelClass)
 */
class Facet extends FacetDefinition {}
