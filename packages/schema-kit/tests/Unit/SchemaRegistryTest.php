<?php

namespace Coda\SchemaKit\Tests\Unit;

use Carbon\Carbon;
use Coda\SchemaKit\Attributes\BelongsTo as BelongsToAttribute;
use Coda\SchemaKit\Attributes\Date as DateFieldAttribute;
use Coda\SchemaKit\Attributes\DateTime as DateTimeFieldAttribute;
use Coda\SchemaKit\Attributes\Taxonomy as TaxonomyAttribute;
use Coda\SchemaKit\Attributes\Textarea as TextareaFieldAttribute;
use Coda\SchemaKit\Attributes\Url as UrlFieldAttribute;
use Coda\SchemaKit\Attributes\WeightedTaxonomy as WeightedTaxonomyAttribute;
use Coda\SchemaKit\Entity;
use Coda\SchemaKit\Facet;
use Coda\SchemaKit\FacetApplicabilityRuleData;
use Coda\SchemaKit\FacetResolutionContext;
use Coda\SchemaKit\FacetSetResolver;
use Coda\SchemaKit\Field;
use Coda\SchemaKit\Attributes\Field as FieldAttribute;
use Coda\SchemaKit\Attributes\MultipleChoice as MultipleChoiceAttribute;
use Coda\SchemaKit\Attributes\Rules as RulesAttribute;
use Coda\SchemaKit\Attributes\Text as TextFieldAttribute;
use Coda\SchemaKit\Computed;
use Coda\SchemaKit\DateInput;
use Coda\SchemaKit\DateTimeInput;
use Coda\SchemaKit\DefinesSchema;
use Coda\SchemaKit\DetailsTabDefinition as DetailsTab;
use Coda\SchemaKit\DetailsViewDefinition as DetailsView;
use Coda\SchemaKit\IdentityColumnDefinition as IdentityColumn;
use Coda\SchemaKit\Identity;
use Coda\SchemaKit\IdentityImage;
use Coda\SchemaKit\IdColumnDefinition as IdColumn;
use Coda\SchemaKit\Relationship;
use Coda\SchemaKit\RepeaterInput;
use Coda\SchemaKit\RadioSegmentedInput;
use Coda\SchemaKit\SchemaRegistry;
use Coda\SchemaKit\ScopeReference;
use Coda\SchemaKit\ScopeDataResolver;
use Coda\SchemaKit\ScopeDefinition;
use Coda\SchemaKit\SelectInput;
use Coda\SchemaKit\SegmentationDefinition;
use Coda\SchemaKit\TextInput;
use Coda\SchemaKit\TableViewDefinition as TableView;
use Coda\SchemaKit\TextareaInput;
use Coda\SchemaKit\TextColumnDefinition as TextColumn;
use Coda\SchemaKit\TreeSelectInput;
use Coda\SchemaKit\UrlInput;
use Coda\SchemaKit\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

class SchemaRegistryTest extends TestCase
{
    public function test_it_resolves_entity_views_in_facet_order(): void
    {
        $entity = Entity::make('attendee')
            ->label('Attendee')
            ->identity(function (Identity $identity): void {
                $identity->title('personName');
            })
            ->facet('general', function (Facet $facet): void {
                $facet->fields(['personName', 'subtitle']);
            })
            ->facet('contact', function (Facet $facet): void {
                $facet->fields(['contactEmail']);
            })
            ->view('cms_edit', function (View $view): void {
                $view->facets(['general', 'contact']);
            });

        $registry = (new SchemaRegistry)->register($entity);
        $resolved = $registry->resolveView('attendee', 'cms_edit');

        $this->assertSame('personName', $resolved->identity()?->getTitle());
        $this->assertSame(['general', 'contact'], array_map(
            fn (Facet $facet) => $facet->name(),
            $resolved->facets(),
        ));
    }

    public function test_facet_field_registration_is_deduplicated_and_first_definition_wins(): void
    {
        $facet = new Facet('contact');

        $facet
            ->fields(['email', 'twitter'])
            ->defineField('email', function (Field $field): void {
                $field->label('Primary Email');
            })
            ->defineField('twitter', function (Field $field): void {
                $field->label('Twitter');
            })
            ->field('email')
            ->defineField('email', function (Field $field): void {
                $field->label('Overridden Email');
            });

        $this->assertSame(['email', 'twitter'], $facet->getFields());
        $this->assertSame('Primary Email', $facet->getFieldDefinitions()['email']->getLabel());
        $this->assertSame('Twitter', $facet->getFieldDefinitions()['twitter']->getLabel());
    }

    public function test_entity_field_lookup_is_deduplicated_across_facets_and_first_definition_wins(): void
    {
        $entity = Entity::make('person')
            ->facet('public_contact', function (Facet $facet): void {
                $facet->defineField('email', function (Field $field): void {
                    $field->label('Public Email');
                });
            })
            ->facet('contact', function (Facet $facet): void {
                $facet->defineField('email', function (Field $field): void {
                    $field->label('Private Email');
                });
                $facet->defineField('telegram', function (Field $field): void {
                    $field->label('Telegram');
                });
            });

        $definitions = $entity->getFieldDefinitions();

        $this->assertSame('Public Email', $definitions['email']->getLabel());
        $this->assertSame('Telegram', $definitions['telegram']->getLabel());
        $this->assertSame('Public Email', $entity->requireFieldDefinition('email')->getLabel());
        $this->assertSame('Private Email', $entity->requireFieldDefinition('contact.email')->getLabel());
    }

    public function test_entity_labels_fall_back_to_humanized_singular_and_plural_names(): void
    {
        $entity = Entity::make('person');

        $this->assertSame('Person', $entity->getLabel());
        $this->assertSame('People', $entity->getPluralLabel());
    }

    public function test_resolved_view_supports_facet_scoped_field_references(): void
    {
        $entity = Entity::make('person')
            ->facet('public_contact', function (Facet $facet): void {
                $facet->defineField('email', function (Field $field): void {
                    $field->label('Public Email');
                });
            })
            ->facet('contact', function (Facet $facet): void {
                $facet->dataPath('data.contact');
                $facet->defineField('email', function (Field $field): void {
                    $field->label('Private Email');
                });
            })
            ->view('cms_edit', function (View $view): void {
                $view->facets(['public_contact', 'contact']);
            });

        $resolved = (new SchemaRegistry)
            ->register($entity)
            ->resolveView('person', 'cms_edit');

        $this->assertSame('Public Email', $resolved->field('email')->getLabel());
        $this->assertSame('Private Email', $resolved->field('contact.email')->getLabel());
        $this->assertSame('email', $resolved->fieldKey('contact.email'));
        $this->assertSame('contact.email', $resolved->valuePath('contact.email'));
    }

    public function test_it_supports_entity_segmentation_with_default_and_scoped_system_segments(): void
    {
        $entity = Entity::make('attendee')
            ->segments(function (SegmentationDefinition $segments): void {
                $segments->group('analytics', function ($group): void {
                    $group
                        ->label('Analytics Segments')
                        ->scope('conference_edition');
                });

                $segments->segment('high_engagement', function ($segment): void {
                    $segment
                        ->label('High Engagement')
                        ->group('analytics')
                        ->predicate('message_count > 10')
                        ->scope('conference_edition');
                });
            });

        $segmentation = $entity->getSegmentation();

        $this->assertNotNull($segmentation);
        $this->assertSame('custom', $segmentation->defaultGroupSlug());
        $this->assertArrayHasKey('custom', $segmentation->getGroups());
        $this->assertSame('Custom Segments', $segmentation->getGroups()['custom']->getLabel());
        $this->assertSame('conference_edition', $segmentation->requireGroup('analytics')->getScopeType());
        $this->assertSame('analytics', $segmentation->requireSegment('high_engagement')->getGroup());
        $this->assertSame('message_count > 10', $segmentation->requireSegment('high_engagement')->getPredicate());
        $this->assertSame('conference_edition', $segmentation->requireSegment('high_engagement')->getScopeType());
    }

    public function test_scope_reference_serialises_generic_scopes(): void
    {
        $empty = ScopeReference::make();
        $edition = ScopeReference::make('conference_edition', 42);

        $this->assertSame([
            'scope_type' => null,
            'scope_id' => null,
        ], $empty->toArray());

        $this->assertSame([
            'scope_type' => 'conference_edition',
            'scope_id' => 42,
        ], $edition->toArray());
    }

    public function test_it_resolves_scope_values_from_explicit_nested_data_paths(): void
    {
        $entity = Entity::make('attendee')
            ->scope('conference_edition', function (ScopeDefinition $scope): void {
                $scope
                    ->field('conference_edition_id')
                    ->attribute('conference_edition_id')
                    ->dataPaths(['attendee.edition.id'])
                    ->setMeta('relation', 'edition');
            });

        $resolved = (new ScopeDataResolver)->resolve($entity, 'conference_edition', [
            'attendee' => [
                'edition' => [
                    'id' => 42,
                ],
            ],
        ]);

        $this->assertSame(42, $resolved);
    }

    public function test_it_resolves_facet_variants_by_taxonomy_term_and_segment(): void
    {
        $entity = Entity::make('attendee')
            ->facet(
                Facet::make('company_manual')
                    ->facetGroup('company')
                    ->excludeFromTaxonomyTerm('roles', 'speaker', 100)
                    ->excludeFromTaxonomyTerm('roles', 'board', 100)
                    ->excludeFromTaxonomyTerm('roles', 'team', 100)
            )
            ->facet(
                Facet::make('company_linked')
                    ->facetGroup('company')
                    ->applicableToTaxonomyTerm('roles', 'speaker', 100)
                    ->applicableToTaxonomyTerm('roles', 'board', 100)
                    ->applicableToTaxonomyTerm('roles', 'team', 100)
            )
            ->facet(
                Facet::make('keynote')
                    ->applicableToSegment('keynote_speakers', 200)
            );

        $resolver = new FacetSetResolver;

        $speakerFacets = $resolver->resolveEntity(
            $entity,
            FacetResolutionContext::make()
                ->taxonomyTerm('roles', 'speaker')
                ->segment('keynote_speakers'),
        );

        $this->assertSame(['company_linked', 'keynote'], array_map(
            static fn (Facet $facet): string => $facet->name(),
            $speakerFacets,
        ));

        $guestFacets = $resolver->resolveEntity(
            $entity,
            FacetResolutionContext::make()->taxonomyTerm('roles', 'guest'),
        );

        $this->assertSame(['company_manual'], array_map(
            static fn (Facet $facet): string => $facet->name(),
            $guestFacets,
        ));
    }

    public function test_it_prefers_highest_priority_matching_rule_and_exclude_wins_ties(): void
    {
        $entity = Entity::make('attendee')
            ->facet(
                Facet::make('default_company')
                    ->facetGroup('company')
                    ->applicability(
                        FacetApplicabilityRuleData::exclude(
                            taxonomyType: 'roles',
                            taxonomyTerm: 'speaker',
                            priority: 50,
                        ),
                    )
            )
            ->facet(
                Facet::make('speaker_company')
                    ->facetGroup('company')
                    ->applicability(
                        FacetApplicabilityRuleData::include(
                            taxonomyType: 'roles',
                            taxonomyTerm: 'speaker',
                            priority: 50,
                        ),
                    )
            )
            ->facet(
                Facet::make('speaker_company_override')
                    ->facetGroup('company')
                    ->applicableToTaxonomyTerm('roles', 'speaker', 75)
            );

        $resolved = (new FacetSetResolver)->resolveEntity(
            $entity,
            FacetResolutionContext::make()->taxonomyTerm('roles', 'speaker'),
        );

        $this->assertSame(['speaker_company_override'], array_map(
            static fn (Facet $facet): string => $facet->name(),
            $resolved,
        ));
    }

    public function test_it_supports_nested_facet_groups_via_dotted_paths(): void
    {
        $entity = Entity::make('attendee')
            ->facet(
                Facet::make('talk_text')
                    ->facetGroup('speaker.talk')
                    ->excludeFromSegment('keynote_speakers', 100)
            )
            ->facet(
                Facet::make('talk_keynote')
                    ->facetGroup('speaker.talk')
                    ->applicableToSegment('keynote_speakers', 100)
            );

        $resolved = (new FacetSetResolver)->resolveEntity(
            $entity,
            FacetResolutionContext::make()->segment('keynote_speakers'),
        );

        $this->assertSame(['talk_keynote'], array_map(
            static fn (Facet $facet): string => $facet->name(),
            $resolved,
        ));
    }

    public function test_it_supports_object_based_registration_and_typed_inputs(): void
    {
        $entity = Entity::make('company')
            ->identity(
                Identity::make()->title('name')
            )
            ->facet(
                Facet::make('general')
                    ->label('General')
                    ->defineField(
                        Field::make('name')
                            ->label('Name')
                            ->input(TextareaInput::make()->rows(4))
                    )
                    ->defineField(
                        Field::make('sourceType')
                            ->label('Source')
                            ->input(
                                RadioSegmentedInput::make()
                                    ->options([
                                        'linked' => 'Linked',
                                        'text' => 'Text',
                                    ])
                                    ->updateOn('live')
                            )
                    )
                    ->defineField(
                        Field::make('search')
                            ->label('Search')
                            ->input(
                                TextInput::make()
                                    ->updateOn('live', 250)
                            )
                    )
                    ->defineField(
                        Field::make('companyId')
                            ->label('Company')
                            ->input(
                                SelectInput::make()
                                    ->searchable()
                                    ->clearable()
                            )
                    )
                    ->defineField(
                        Field::make('topicTagIds')
                            ->label('Topics')
                            ->input(
                                TreeSelectInput::make()
                                    ->searchable()
                                    ->clearable()
                                    ->multiple()
                                    ->leafOnly()
                            )
                    )
                    ->defineField(
                        Field::make('attendees')
                            ->label('Attendees')
                            ->input(
                                RepeaterInput::make()->schema(['a', 'b'])
                            )
                    )
            )
            ->view(
                View::make('cms_edit')
                    ->facets(['general'])
            );

        $resolved = (new SchemaRegistry)
            ->register($entity)
            ->resolveView('company', 'cms_edit');

        $this->assertSame('name', $resolved->identity()?->getTitle());
        $this->assertInstanceOf(TextareaInput::class, $resolved->field('name')->getInput());
        $this->assertInstanceOf(RadioSegmentedInput::class, $resolved->field('sourceType')->getInput());
        $this->assertSame('live', $resolved->field('sourceType')->getInput()?->getUpdateOn());
        $this->assertInstanceOf(SelectInput::class, $resolved->field('companyId')->getInput());
        $this->assertInstanceOf(TreeSelectInput::class, $resolved->field('topicTagIds')->getInput());
        $this->assertInstanceOf(RepeaterInput::class, $resolved->field('attendees')->getInput());
        $this->assertSame('live', $resolved->field('search')->getInput()?->getUpdateOn());
        $this->assertSame(250, $resolved->field('search')->getInput()?->getDebounceUpdate());
    }

    public function test_it_supports_dto_backed_facets_and_inferred_defaults(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestProfileData::class)
            ->dataPath('data.profile')
            ->defineField('website', fn (Field $field) => $field->help('Public profile'))
            ->defineComputed(
                Computed::make('updatedAt')
                    ->formVisible(false)
            );

        $definitions = $facet->getFieldDefinitions();

        $this->assertSame('data.profile', $facet->getDataPath());
        $this->assertArrayHasKey('displayName', $definitions);
        $this->assertArrayHasKey('website', $definitions);
        $this->assertSame('Website', $definitions['website']->getLabel());
        $this->assertSame('Public profile', $definitions['website']->getHelp());
        $this->assertSame('computed', $definitions['updatedAt']->definitionType());
    }

    public function test_it_imports_schema_members_from_data_classes(): void
    {
        $facet = Facet::make('profile')
            ->data(TestProfileSchemaData::class, 'data.profile')
            ->fields([
                'displayName',
            ]);

        $definitions = $facet->getFieldDefinitions();

        $this->assertSame('data.profile', $facet->getDataPath());
        $this->assertSame('Website', $definitions['website']->getLabel());
        $this->assertSame('nullable|email', $definitions['contactEmail']->getRules());
        $this->assertSame('relationship', $definitions['companyId']->definitionType());
        $this->assertSame('belongs_to', $definitions['companyId']->getRelationshipType());
        $this->assertSame('computed', $definitions['updatedAt']->definitionType());
    }

    public function test_it_infers_spatie_validation_attributes_for_fields(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestValidatedProfileData::class);

        $definitions = $facet->getFieldDefinitions();

        $this->assertTrue($definitions['forename']->isRequired());
        $this->assertSame(['required'], $definitions['forename']->getRules());
        $this->assertSame(['nullable'], $definitions['honorific']->getRules());
        $this->assertSame(['nullable', 'date', 'date_format:Y-m-d'], $definitions['birthdate']->getRules());
        $this->assertInstanceOf(DateInput::class, $definitions['birthdate']->getInput());
    }

    public function test_it_supports_schema_rules_attributes(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestRuledProfileData::class);

        $definitions = $facet->getFieldDefinitions();

        $this->assertTrue($definitions['forename']->isRequired());
        $this->assertSame(['required', 'max:255'], $definitions['forename']->getRules());
        $this->assertSame(['nullable', 'email:rfc'], $definitions['contactEmail']->getRules());
    }

    public function test_it_supports_typed_field_attributes_for_inputs_and_dates(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestTypedFieldAttributeData::class);

        $definitions = $facet->getFieldDefinitions();

        $this->assertInstanceOf(TextInput::class, $definitions['website']->getInput());
        $this->assertSame(['nullable', 'max:500'], $definitions['website']->getRules());
        $this->assertInstanceOf(SelectInput::class, $definitions['tags']->getInput());
        $this->assertTrue($definitions['tags']->getInput()->isMultiple());
        $this->assertSame(['array'], $definitions['tags']->getRules());
        $this->assertInstanceOf(DateInput::class, $definitions['birthdate']->getInput());
        $this->assertSame(['nullable', 'date', 'date_format:Y-m-d', 'after:1900-01-01'], $definitions['birthdate']->getRules());
        $this->assertInstanceOf(DateTimeInput::class, $definitions['publishedAt']->getInput());
        $this->assertSame(['nullable', 'date', 'date_format:Y-m-d\TH:i', 'after:2020-01-01T00:00'], $definitions['publishedAt']->getRules());
    }

    public function test_it_supports_relationship_and_specialized_field_attributes(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestAttributedRelationshipData::class);

        $definitions = $facet->getFieldDefinitions();

        $this->assertSame('relationship', $definitions['company']->definitionType());
        $this->assertSame('belongs_to', $definitions['company']->getRelationshipType());
        $this->assertSame('company', $definitions['company']->getTargetEntity());
        $this->assertSame('relationship', $definitions['role']->definitionType());
        $this->assertSame('taxonomy', $definitions['role']->getRelationshipType());
        $this->assertSame('relationship', $definitions['topics']->definitionType());
        $this->assertSame('weighted_taxonomy', $definitions['topics']->getRelationshipType());
        $this->assertTrue($definitions['topics']->isMultiple());
        $this->assertInstanceOf(UrlInput::class, $definitions['website']->getInput());
        $this->assertInstanceOf(TextareaInput::class, $definitions['bio']->getInput());
    }

    public function test_it_ignores_non_schema_property_only_attributes_on_promoted_parameters(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestDateCastProfileData::class);

        $definitions = $facet->getFieldDefinitions();

        $this->assertArrayHasKey('birthdate', $definitions);
        $this->assertInstanceOf(DateInput::class, $definitions['birthdate']->getInput());
    }

    public function test_it_applies_schema_field_attributes_and_allows_overrides(): void
    {
        $facet = Facet::make('profile')
            ->dataClass(TestAttributedProfileData::class)
            ->defineField('displayName', fn (Field $field) => $field->label('Speaker'));

        $definitions = $facet->getFieldDefinitions();

        $this->assertSame('Title such as Dr., Mr., or Ms.', $definitions['honorific']->getHelp());
        $this->assertSame('Speaker', $definitions['displayName']->getLabel());
        $this->assertSame('display_name', $definitions['displayName']->getAttribute());
        $this->assertTrue($definitions['displayName']->isTitle());
        $this->assertTrue($definitions['displayName']->isModal());
        $this->assertFalse($definitions['displayName']->isWritable());
    }

    public function test_it_registers_facets_from_dto_classes(): void
    {
        $entity = Entity::make('profile')
            ->facet(TestFacetBackedData::class)
            ->facet('meta', function (Facet $facet): void {
                $facet->fields(['updatedAt']);
            });

        $facet = $entity->requireFacet('profile');

        $this->assertSame('Profile', $facet->getLabel());
        $this->assertSame('data.profile', $facet->getDataPath());
        $this->assertArrayHasKey('displayName', $facet->getFieldDefinitions());
        $this->assertArrayHasKey('website', $facet->getFieldDefinitions());
        $this->assertArrayHasKey('profile', $entity->getFacets());
        $this->assertArrayHasKey('meta', $entity->getFacets());
    }

    public function test_it_imports_facets_from_other_registered_schemas_and_rebinds_them_locally(): void
    {
        $person = Entity::make('person')
            ->facet(
                TestFacetBackedData::make()
                    ->form(fn ($form) => $form->label('General')->view('form.person'))
                    ->details(fn ($details) => $details->fieldset('general'))
            )
            ->view(
                View::make('cms_edit')
                    ->facets(['profile'])
            );

        $attendee = Entity::make('attendee')
            ->view(
                View::make('cms_edit')
                    ->facets(['person'])
            );

        $attendee->importFacet('person.profile', 'person')
            ->label('Person General')
            ->section(tab: 'Person', label: 'General', view: 'form.person');

        $registry = (new SchemaRegistry)
            ->register($person)
            ->register($attendee);

        $facet = $registry->entity('attendee')->requireFacet('person');
        $resolved = $registry->resolveView('attendee', 'cms_edit');

        $this->assertSame('Person General', $facet->getLabel());
        $this->assertSame('data.person', $facet->getDataPath());
        $this->assertSame(TestFacetBackedData::class, $facet->getDataClass());
        $this->assertNull($facet->getFieldDefinitions()['website']->getHelp());
        $this->assertSame('form.person', $facet->getForm()?->getView());
        $this->assertSame('General', $facet->getForm()?->getLabel());
        $this->assertSame('person', $facet->getDetails()?->getFieldset());
        $this->assertSame(['person'], array_map(
            fn (Facet $resolvedFacet) => $resolvedFacet->name(),
            $resolved->facets(),
        ));
    }

    public function test_it_infers_relationships_from_the_owning_model_and_humanizes_default_labels(): void
    {
        $entity = Entity::make('profile')
            ->model(TestSchemaModel::class)
            ->facet('profile', function (Facet $facet): void {
                $facet->data(TestProfileWithRelationsData::class, 'data.profile');
            });

        $definitions = $entity->requireFacet('profile')->getFieldDefinitions();

        $this->assertSame('relationship', $definitions['companyId']->definitionType());
        $this->assertSame('belongs_to', $definitions['companyId']->getRelationshipType());
        $this->assertSame('Company', $definitions['companyId']->getLabel());
        $this->assertSame('Profile Picture', $definitions['profile_picture']->getLabel());
    }

    public function test_it_supports_relationships_identity_images_and_typed_view_definitions(): void
    {
        $entity = Entity::make('speaker')
            ->identity(
                Identity::make()
                    ->title('displayName')
                    ->image(
                        IdentityImage::make('avatarUrl')
                            ->mediaUuid('avatarUuid')
                            ->mediaVersion('avatarVersion')
                            ->preset('square')
                            ->widths([40, 80])
                            ->sizes('40px')
                            ->square()
                    )
            )
            ->facet(
                Facet::make('general')
                    ->defineField(Field::make('displayName')->title()->modal())
                    ->defineRelationship(
                        Relationship::make('companyId')
                            ->to('company')
                            ->relationshipType('belongs_to')
                    )
                    ->defineComputed(Computed::make('avatarUrl')->formVisible(false))
            )
            ->view(
                View::make('cms_list')
                    ->facets(['general'])
                    ->table(
                        TableView::make()->columns([
                            IdColumn::make(),
                            IdentityColumn::make('Speaker')->sortAs('displayName'),
                            TextColumn::make('companyId')->label('Company'),
                        ])
                    )
            )
            ->view(
                View::make('cms_details')
                    ->facets(['general'])
                    ->details(
                        DetailsView::make()->tab(
                            DetailsTab::make('Details')->left(['general'])
                        )
                    )
            );

        $resolvedList = (new SchemaRegistry)
            ->register($entity)
            ->resolveView('speaker', 'cms_list');

        $resolvedDetails = (new SchemaRegistry)
            ->register($entity)
            ->resolveView('speaker', 'cms_details');

        $this->assertSame('relationship', $resolvedList->field('companyId')->definitionType());
        $this->assertSame('avatarUrl', $resolvedList->identity()?->getImageDefinition()?->getField());
        $this->assertSame('identity', $resolvedList->view()->getTable()?->getColumns()[1]->type());
        $this->assertSame('Details', $resolvedDetails->view()->getDetails()?->getTabs()[0]->title());
    }
}

final class TestProfileData
{
    public function __construct(
        public string $displayName = '',
        public ?string $website = null,
    ) {}
}

final class TestProfileSchemaData implements DefinesSchema
{
    public static function schema(): array
    {
        return [
            'fields' => [
                'displayName',
                Field::make('website')->label('Website')->help('Public profile'),
                Field::make('contactEmail')->label('Contact Email')->rules('nullable|email'),
            ],
            'relationships' => [
                Relationship::make('companyId')->to('company')->belongsTo(),
            ],
            'computed' => [
                Computed::make('updatedAt')->formVisible(false),
            ],
        ];
    }
}

final class TestFacetBackedData implements DefinesSchema
{
    public static function make(): Facet
    {
        return Facet::make('profile')
            ->label('Profile')
            ->data(static::class, 'data.profile');
    }

    public static function schema(): array
    {
        return [
            'fields' => [
                Field::make('displayName')->label('Display Name'),
                Field::make('website')->label('Website'),
            ],
        ];
    }
}

final class TestProfileWithRelationsData
{
    public function __construct(
        public ?int $companyId = null,
        public ?string $profile_picture = null,
    ) {}
}

final class TestValidatedProfileData
{
    public function __construct(
        public ?string $honorific = null,
        #[Required]
        public string $forename = '',
        #[DateFieldAttribute]
        public ?Carbon $birthdate = null,
    ) {}
}

final class TestDateCastProfileData
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public ?Carbon $birthdate = null,
    ) {}
}

final class TestRuledProfileData
{
    public function __construct(
        #[RulesAttribute('required|max:255')]
        public ?string $forename = null,
        #[RulesAttribute('nullable|email')]
        public ?string $contactEmail = null,
    ) {}
}

final class TestTypedFieldAttributeData
{
    public function __construct(
        #[TextFieldAttribute(rules: 'max:500')]
        public ?string $website = null,
        #[MultipleChoiceAttribute(options: ['a' => 'A', 'b' => 'B'], searchable: true, rules: 'array')]
        public array $tags = [],
        #[DateFieldAttribute(rules: 'after:1900-01-01')]
        public ?Carbon $birthdate = null,
        #[DateTimeFieldAttribute(rules: 'after:2020-01-01T00:00')]
        public ?Carbon $publishedAt = null,
    ) {}
}

final class TestAttributedProfileData
{
    public function __construct(
        #[FieldAttribute(help: 'Title such as Dr., Mr., or Ms.')]
        public ?string $honorific = null,
        #[FieldAttribute(label: 'Person', attribute: 'display_name', title: true, modal: true, writable: false)]
        public string $displayName = '',
    ) {}
}

final class TestAttributedRelationshipData
{
    public function __construct(
        #[BelongsToAttribute('company')]
        public ?int $company = null,
        #[TaxonomyAttribute('edition_role')]
        public ?int $role = null,
        #[WeightedTaxonomyAttribute('edition_topic', rules: 'array')]
        public array $topics = [],
        #[UrlFieldAttribute]
        public ?string $website = null,
        #[TextareaFieldAttribute]
        public ?string $bio = null,
    ) {}
}

final class TestRelatedModel extends Model
{
    protected $table = 'test_related_models';
}

final class TestSchemaModel extends Model
{
    protected $table = 'test_schema_models';

    public function company(): BelongsTo
    {
        return $this->belongsTo(TestRelatedModel::class, 'company_id');
    }
}
