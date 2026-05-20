<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Form\Fields\ImageUpload;
use Coda\FormKit\Field;
use Coda\FormKit\Fields\Date as DateField;
use Coda\FormKit\Fields\RadioSegmented;
use Coda\FormKit\Fields\Repeater;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text as TextField;
use Coda\FormKit\Fields\Textarea;
use Coda\FormKit\Fields\Tree;
use Coda\FormKit\Fields\Url;
use Coda\SchemaKit\DateInput;
use Coda\SchemaKit\DateTimeInput;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\ImageUploadInput;
use Coda\SchemaKit\InputDefinition;
use Coda\SchemaKit\RadioSegmentedInput;
use Coda\SchemaKit\RepeaterInput as SchemaRepeaterInput;
use Coda\SchemaKit\SelectInput as SchemaSelectInput;
use Coda\SchemaKit\TextareaInput;
use Coda\SchemaKit\TreeInput as SchemaTreeInput;
use Coda\SchemaKit\TreeSelectInput as SchemaTreeSelectInput;
use Coda\SchemaKit\UrlInput as SchemaUrlInput;
use Coda\SchemaKit\WeightedCategoryTreeInput as SchemaWeightedCategoryTreeInput;
use RuntimeException;

class SchemaFormFieldFactory
{
    public function make(FieldDefinition $field): Field
    {
        $instance = $this->makeFormField($field);

        if ($field->getLabel() !== null) {
            $instance->label($field->getLabel());
        }

        if ($field->getHelp() !== null) {
            $instance->help($field->getHelp());
        }

        if ($field->isRequired()) {
            $instance->required();
        }

        if ($field->getRules() !== null) {
            $instance->rules($field->getRules());
        }

        if (method_exists($instance, 'placeholder') && $field->getPlaceholder() !== null) {
            $instance->placeholder($field->getPlaceholder());
        }

        $this->applyFieldMeta($instance, $field);

        return $instance;
    }

    protected function defaultImageUploadFieldClass(): string
    {
        return ImageUpload::class;
    }

    protected function defaultWeightedCategoryTreeFieldClass(): ?string
    {
        return null;
    }

    private function makeFormField(FieldDefinition $field): Field
    {
        $input = $field->getInput();

        if ($input instanceof InputDefinition) {
            return $this->makeTypedInputField($field, $input);
        }

        if ($field->getFormType() === 'image_upload') {
            return $this->imageUploadField($field);
        }

        if ($field->getFormType() === 'weighted_category_tree') {
            return $this->weightedCategoryTreeField($field->name(), $field->getMeta('factory'));
        }

        $factory = $field->getMeta('factory');

        if (is_string($factory) && class_exists($factory) && method_exists($factory, 'make')) {
            return $factory::make($field->name());
        }

        return match ($field->getFormType()) {
            'date' => DateField::make($field->name()),
            'datetime' => TextField::make($field->name())->inputType('datetime-local'),
            'textarea' => Textarea::make($field->name()),
            'url' => Url::make($field->name()),
            'select' => Select::make($field->name()),
            'tree' => Tree::make($field->name()),
            'repeater' => Repeater::make($field->name()),
            'radio_segmented' => RadioSegmented::make($field->name()),
            default => TextField::make($field->name()),
        };
    }

    private function makeTypedInputField(FieldDefinition $field, InputDefinition $input): Field
    {
        return match (true) {
            $input instanceof ImageUploadInput => $this->imageUploadFieldFromInput($field, $input),
            $input instanceof TextareaInput => Textarea::make($field->name()),
            $input instanceof SchemaUrlInput => Url::make($field->name()),
            $input instanceof DateInput => DateField::make($field->name()),
            $input instanceof DateTimeInput => TextField::make($field->name())->inputType('datetime-local'),
            $input instanceof SchemaSelectInput => Select::make($field->name()),
            $input instanceof SchemaTreeSelectInput => Select::make($field->name()),
            $input instanceof SchemaTreeInput => Tree::make($field->name()),
            $input instanceof SchemaRepeaterInput => Repeater::make($field->name()),
            $input instanceof RadioSegmentedInput => RadioSegmented::make($field->name()),
            $input instanceof SchemaWeightedCategoryTreeInput => $this->weightedCategoryTreeField($field->name(), $input->getFactory()),
            default => TextField::make($field->name()),
        };
    }

    private function applyFieldMeta(Field $instance, FieldDefinition $field): void
    {
        $meta = $field->allMeta();
        $input = $field->getInput();

        if ($input instanceof InputDefinition) {
            $this->applyTypedInput($instance, $input);
        }

        if (array_key_exists('default', $meta) && method_exists($instance, 'default')) {
            $instance->default($meta['default']);
        }

        if (is_string($meta['show'] ?? null) && method_exists($instance, 'show')) {
            $instance->show($meta['show']);
        }

        if (array_key_exists('live', $meta) && method_exists($instance, 'live')) {
            $instance->live((bool) $meta['live']);
        }

        if (array_key_exists('blur', $meta) && method_exists($instance, 'blur')) {
            $instance->blur((bool) $meta['blur']);
        }

        if (is_int($meta['debounce'] ?? null) && method_exists($instance, 'debounce')) {
            $instance->debounce($meta['debounce']);
        }

        if (is_array($meta['options'] ?? null) && method_exists($instance, 'options')) {
            $instance->options($meta['options']);
        }

        if (($meta['options_using'] ?? null) instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($meta['options_using']);
        }

        if (is_string($meta['variant'] ?? null) && method_exists($instance, 'variant')) {
            $instance->variant($meta['variant']);
        }

        if (is_string($meta['size'] ?? null) && method_exists($instance, 'size')) {
            $instance->size($meta['size']);
        }

        if (array_key_exists('searchable', $meta) && method_exists($instance, 'searchable')) {
            $instance->searchable((bool) $meta['searchable']);
        }

        if (array_key_exists('clearable', $meta) && method_exists($instance, 'clearable')) {
            $instance->clearable((bool) $meta['clearable']);
        }

        if (array_key_exists('multiple', $meta) && method_exists($instance, 'multiple')) {
            $instance->multiple((bool) $meta['multiple']);
        }

        if (is_array($meta['schema'] ?? null) && method_exists($instance, 'schema')) {
            $instance->schema($meta['schema']);
        }

        if (is_string($meta['create_option'] ?? null) && method_exists($instance, 'createOption')) {
            $instance->createOption(
                $meta['create_option'],
                is_string($meta['create_option_label'] ?? null) ? $meta['create_option_label'] : 'Create',
            );
        }

        if (is_string($meta['create_option_modal_title'] ?? null) && method_exists($instance, 'createOptionModalTitle')) {
            $instance->createOptionModalTitle($meta['create_option_modal_title']);
        }

        if (($meta['create_option_seed_data_using'] ?? null) instanceof \Closure && method_exists($instance, 'createOptionSeedDataUsing')) {
            $instance->createOptionSeedDataUsing($meta['create_option_seed_data_using']);
        }

        if (array_key_exists('tree', $meta) && (bool) $meta['tree'] && method_exists($instance, 'tree')) {
            $instance->tree();
        }

        if (($meta['tree_options_using'] ?? null) instanceof \Closure && method_exists($instance, 'treeOptionsUsing')) {
            $instance->treeOptionsUsing($meta['tree_options_using']);
        }

        if (is_array($meta['tree_options'] ?? null) && method_exists($instance, 'treeOptions')) {
            $instance->treeOptions($meta['tree_options']);
        }

        if (array_key_exists('tree_leaf_only', $meta) && method_exists($instance, 'treeLeafOnly')) {
            $instance->treeLeafOnly((bool) $meta['tree_leaf_only']);
        }

        if (array_key_exists('tree_exclude_root', $meta)) {
            if (method_exists($instance, 'treeExcludeRoot')) {
                $instance->treeExcludeRoot((bool) $meta['tree_exclude_root']);
            } elseif (method_exists($instance, 'excludeRoot')) {
                $instance->excludeRoot((bool) $meta['tree_exclude_root']);
            }
        }

        if (is_string($meta['topic_type'] ?? null) && method_exists($instance, 'topicType')) {
            $instance->topicType($meta['topic_type']);
        }

        if (is_array($meta['range'] ?? null) && method_exists($instance, 'range')) {
            $range = $meta['range'];
            $instance->range(
                $range[0] ?? 1,
                $range[1] ?? 5,
                $range[2] ?? 1,
                $range[3] ?? null,
            );
        }

        if (is_array($meta['ticks'] ?? null) && method_exists($instance, 'ticks')) {
            $instance->ticks($meta['ticks']);
        }

        if (is_string($meta['empty_text'] ?? null) && method_exists($instance, 'emptyText')) {
            $instance->emptyText($meta['empty_text']);
        }

        if (is_string($meta['topic_label'] ?? null) && method_exists($instance, 'topicLabel')) {
            $instance->topicLabel($meta['topic_label']);
        }

        if (is_string($meta['score_label'] ?? null) && method_exists($instance, 'scoreLabel')) {
            $instance->scoreLabel($meta['score_label']);
        }
    }

    private function applyTypedInput(Field $instance, InputDefinition $input): void
    {
        if ($input->getDefault() !== null && method_exists($instance, 'default')) {
            $instance->default($input->getDefault());
        }

        if (is_string($input->getVisibleWhen()) && $input->getVisibleWhen() !== '' && method_exists($instance, 'show')) {
            $instance->show($input->getVisibleWhen());
        }

        if ($input->isReadonly() && method_exists($instance, 'readonly')) {
            $instance->readonly();
        }

        if ($input instanceof SchemaSelectInput) {
            $this->applySelectInput($instance, $input);
        } elseif ($input instanceof SchemaTreeSelectInput) {
            $this->applyTreeSelectInput($instance, $input);
        } elseif ($input instanceof SchemaTreeInput) {
            $this->applyTreeInput($instance, $input);
        } elseif ($input instanceof SchemaRepeaterInput) {
            $this->applyRepeaterInput($instance, $input);
        } elseif ($input instanceof RadioSegmentedInput) {
            $this->applyRadioSegmentedInput($instance, $input);
        } elseif ($input instanceof SchemaWeightedCategoryTreeInput) {
            $this->applyWeightedCategoryTreeInput($instance, $input);
        } elseif ($input instanceof TextareaInput) {
            if (is_int($input->getRows()) && method_exists($instance, 'rows')) {
                $instance->rows($input->getRows());
            }
        }
    }

    private function applySelectInput(Field $instance, SchemaSelectInput $input): void
    {
        $options = $input->getOptions();

        if (is_array($options) && method_exists($instance, 'options')) {
            $instance->options($options);
        }

        if ($options instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($options);
        }

        if ($input->getVariant() !== null && method_exists($instance, 'variant')) {
            $instance->variant($input->getVariant());
        }

        if ($input->isSearchable() && method_exists($instance, 'searchable')) {
            $instance->searchable();
        }

        if ($input->isClearable() && method_exists($instance, 'clearable')) {
            $instance->clearable();
        }

        if ($input->isMultiple() && method_exists($instance, 'multiple')) {
            $instance->multiple();
        }
    }

    private function applyTreeInput(Field $instance, SchemaTreeInput $input): void
    {
        $options = $input->getOptions();

        if ($options instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($options);
        } elseif ($options instanceof \Closure && method_exists($instance, 'treeOptionsUsing')) {
            $instance->treeOptionsUsing($options);
        }

        if (is_array($options) && method_exists($instance, 'treeOptions')) {
            $instance->treeOptions($options);
        } elseif (is_array($options) && method_exists($instance, 'options')) {
            $instance->options($options);
        }

        if ($input->isSearchable() && method_exists($instance, 'searchable')) {
            $instance->searchable();
        }

        if ($input->isLeafOnly() && method_exists($instance, 'treeLeafOnly')) {
            $instance->treeLeafOnly();
        }

        if ($input->isExcludeRoot()) {
            if (method_exists($instance, 'treeExcludeRoot')) {
                $instance->treeExcludeRoot(true);
            } elseif (method_exists($instance, 'excludeRoot')) {
                $instance->excludeRoot(true);
            }
        }
    }

    private function applyTreeSelectInput(Field $instance, SchemaTreeSelectInput $input): void
    {
        if (method_exists($instance, 'tree')) {
            $instance->tree();
        }

        $options = $input->getOptions();

        if ($options instanceof \Closure && method_exists($instance, 'treeOptionsUsing')) {
            $instance->treeOptionsUsing($options);
        } elseif ($options instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($options);
        }

        if (is_array($options) && method_exists($instance, 'treeOptions')) {
            $instance->treeOptions($options);
        } elseif (is_array($options) && method_exists($instance, 'options')) {
            $instance->options($options);
        }

        if ($input->isSearchable() && method_exists($instance, 'searchable')) {
            $instance->searchable();
        }

        if ($input->isClearable() && method_exists($instance, 'clearable')) {
            $instance->clearable();
        }

        if ($input->isMultiple() && method_exists($instance, 'multiple')) {
            $instance->multiple();
        }

        if ($input->isLeafOnly() && method_exists($instance, 'treeLeafOnly')) {
            $instance->treeLeafOnly();
        }

        if ($input->isExcludeRoot() && method_exists($instance, 'treeExcludeRoot')) {
            $instance->treeExcludeRoot();
        }
    }

    private function applyRadioSegmentedInput(Field $instance, RadioSegmentedInput $input): void
    {
        $options = $input->getOptions();

        if (is_array($options) && method_exists($instance, 'options')) {
            $instance->options($options);
        }

        if ($options instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($options);
        }

        if ($input->isLive() && method_exists($instance, 'live')) {
            $instance->live();
        }
    }

    private function applyRepeaterInput(Field $instance, SchemaRepeaterInput $input): void
    {
        if ($input->getSchema() !== [] && method_exists($instance, 'schema')) {
            $instance->schema($input->getSchema());
        }
    }

    private function applyWeightedCategoryTreeInput(Field $instance, SchemaWeightedCategoryTreeInput $input): void
    {
        $options = $input->getOptions();

        if ($options instanceof \Closure && method_exists($instance, 'optionsUsing')) {
            $instance->optionsUsing($options);
        }

        if (is_array($options) && method_exists($instance, 'options')) {
            $instance->options($options);
        }

        if (is_string($input->getTopicType()) && method_exists($instance, 'topicType')) {
            $instance->topicType($input->getTopicType());
        }

        if (is_array($input->getRange()) && method_exists($instance, 'range')) {
            $range = $input->getRange();
            $instance->range($range[0] ?? 1, $range[1] ?? 5, $range[2] ?? 1, $range[3] ?? null);
        }

        if (is_array($input->getTicks()) && method_exists($instance, 'ticks')) {
            $instance->ticks($input->getTicks());
        }

        if (is_string($input->getEmptyText()) && method_exists($instance, 'emptyText')) {
            $instance->emptyText($input->getEmptyText());
        }
    }

    private function imageUploadField(FieldDefinition $field): Field
    {
        $factory = $field->getMeta('factory');
        $class = is_string($factory) && class_exists($factory) ? $factory : $this->defaultImageUploadFieldClass();

        /** @var Field $upload */
        $upload = $class::make($field->name());

        $upload->collection((string) $field->getMeta('collection', 'default'));
        $upload->single((bool) $field->getMeta('single', false));
        $previewPreset = $field->getMeta('preview_preset');

        $upload->previewPreset(
            is_string($previewPreset) && $previewPreset !== '' ? $previewPreset : null,
            is_numeric($field->getMeta('preview_preset_width')) ? (int) $field->getMeta('preview_preset_width') : null,
        );

        if (is_numeric($field->getMeta('max_file_size'))) {
            $upload->maxFileSize((int) $field->getMeta('max_file_size'));
        }

        if (is_string($field->getMeta('dropzone_text')) && $field->getMeta('dropzone_text') !== '') {
            $upload->dropzoneText($field->getMeta('dropzone_text'));
        }

        return $upload;
    }

    private function imageUploadFieldFromInput(FieldDefinition $field, ImageUploadInput $input): Field
    {
        $class = is_string($input->getFactory()) && class_exists($input->getFactory())
            ? $input->getFactory()
            : $this->defaultImageUploadFieldClass();

        /** @var Field $upload */
        $upload = $class::make($field->name());
        $upload->collection((string) ($input->getCollection() ?: 'default'));
        $upload->single($input->isSingle());
        $upload->previewPreset($input->getPreviewPreset(), $input->getPreviewPresetWidth());

        if (is_int($input->getMaxFileSize())) {
            $upload->maxFileSize($input->getMaxFileSize());
        }

        if (is_string($input->getDropzoneText()) && $input->getDropzoneText() !== '') {
            $upload->dropzoneText($input->getDropzoneText());
        }

        return $upload;
    }

    private function weightedCategoryTreeField(string $name, mixed $factory): Field
    {
        $class = is_string($factory) && class_exists($factory)
            ? $factory
            : $this->defaultWeightedCategoryTreeFieldClass();

        if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'make')) {
            throw new RuntimeException('Weighted category tree fields require an explicit factory or shared default class.');
        }

        return $class::make($name);
    }
}
