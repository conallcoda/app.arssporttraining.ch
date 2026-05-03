<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CategoryBlockFormData;
use App\Data\Training\Calendar\NoteBlockFormData;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Users\UserGroup;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Coda\FormKit\FormFieldset;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class BlockForm extends FormModal
{
    public ?int $groupId = null;

    public ?int $userId = null;

    public ?int $editingBlockId = null;

    public bool $isEditing = false;

    public array $members = [];

    public array $selectedMembers = [];

    public ?int $categoryId = null;

    public ?string $categorySlug = null;

    public ?string $categoryName = null;

    public ?int $parentBlockId = null;

    public array $categoryOptions = [];

    public function mount(
        string $name = 'block',
        string $title = 'add-block-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'add-block-default' ? __('Add Block') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            contextData: $contextData,
            excludeFields: $excludeFields,
        );
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->groupId = $data['groupId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->editingBlockId = $data['blockId'] ?? null;
        $this->isEditing = $this->editingBlockId !== null;
        $this->categoryId = $data['categoryId'] ?? null;
        $this->categorySlug = $data['categorySlug'] ?? null;
        $this->categoryName = $data['categoryName'] ?? null;
        $this->parentBlockId = $data['parentId'] ?? null;
        $this->categoryOptions = $data['categoryOptions'] ?? [];

        if ($this->categoryId === null && ! empty($this->categoryOptions)) {
            $firstKey = array_key_first($this->categoryOptions);
            $this->categoryId = $firstKey;
            $this->categorySlug = $this->categoryOptions[$firstKey]['slug'] ?? null;
            $this->categoryName = $this->categoryOptions[$firstKey]['name'] ?? null;
        }

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        if ($this->categoryId !== null) {
            $this->data = [
                'type' => 'category',
                'category_id' => $this->categoryId,
                'start' => $data['date'] ?? '',
                'end' => $data['endDate'] ?? '',
                'note' => '',
                'color' => null,
                'config' => $this->categorySlug === 'strength' ? ['goal' => 10, 'autoRecord1rm' => true] : [],
            ];
        } else {
            $defaultType = 'focus';
            $defaultColor = TrainingProgramBlockTypeEnum::from($defaultType)->blockTypeClass()::defaultColor();

            $this->data = [
                'type' => $defaultType,
                'start' => $data['date'] ?? '',
                'end' => $data['endDate'] ?? '',
                'note' => '',
                'color' => $defaultColor,
            ];
        }

        if ($this->isEditing) {
            $existingBlock = TrainingProgramBlock::with('category')->find($this->editingBlockId);
            if ($existingBlock) {
                $this->categoryId = $existingBlock->category_id;
                $this->categorySlug = $existingBlock->category?->slug;
                $this->categoryName = $existingBlock->category?->name;

                $this->data = [
                    'type' => $existingBlock->type->value,
                    'start' => $existingBlock->start->format('Y-m-d'),
                    'end' => $existingBlock->end?->format('Y-m-d') ?? '',
                    'note' => $existingBlock->note,
                    'color' => $existingBlock->color,
                ];

                if ($existingBlock->config) {
                    $this->data['config'] = $existingBlock->config->toArray();
                }
            }
        } elseif ($this->parentBlockId !== null) {
            $parentBlock = TrainingProgramBlock::with('category')->find($this->parentBlockId);
            if ($parentBlock) {
                $this->categoryId = $parentBlock->category_id;
                $this->categorySlug = $parentBlock->category?->slug;
                $this->categoryName = $parentBlock->category?->name;

                $this->data = [
                    'type' => $parentBlock->type->value,
                    'start' => $parentBlock->start->format('Y-m-d'),
                    'end' => $parentBlock->end?->format('Y-m-d') ?? '',
                    'note' => $parentBlock->note,
                    'color' => $parentBlock->color,
                ];

                if ($parentBlock->config) {
                    $this->data['config'] = $parentBlock->config->toArray();
                }
            }
        }

        $isNote = $this->categoryId === null;
        $isExistingBlockContext = $this->isEditing || $this->parentBlockId !== null;
        if ($title !== null) {
            $this->activeTitle = $title;
        } elseif ($isNote) {
            $this->activeTitle = $this->isEditing ? __('Edit Note') : __('Add Note');
        } else {
            $this->activeTitle = $isExistingBlockContext ? __('Edit Block') : __('Add Block');
        }

        $this->loadMembers();

        Flux::modal($this->name)->show();
    }

    public function updatedDataType(string $value): void
    {
        $this->data['color'] = TrainingProgramBlockTypeEnum::from($value)->blockTypeClass()::defaultColor();

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;
    }

    public function updatedDataCategoryId(int $value): void
    {
        if (! isset($this->categoryOptions[$value])) {
            return;
        }

        $this->categoryId = $value;
        $this->categorySlug = $this->categoryOptions[$value]['slug'] ?? null;
        $this->categoryName = $this->categoryOptions[$value]['name'] ?? null;
        $this->data['config'] = $this->categorySlug === 'strength' ? ['goal' => 10, 'autoRecord1rm' => true] : [];

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;
    }

    protected function loadMembers(): void
    {
        $this->members = [];
        $this->selectedMembers = [];

        if ($this->groupId === null || $this->userId !== null) {
            return;
        }

        $group = UserGroup::with('members')->find($this->groupId);
        if ($group === null) {
            return;
        }

        $this->members = $group->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->all();

        $allMemberIds = array_column($this->members, 'id');

        if (! $this->isEditing) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $existingBlock = TrainingProgramBlock::find($this->editingBlockId);
        if ($existingBlock === null) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        if ($existingBlock->category_id !== null) {
            $inactiveUserIds = TrainingProgramBlock::query()
                ->where('parent_id', $this->editingBlockId)
                ->where('active', false)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $this->selectedMembers = array_values(
                array_diff(array_map('strval', $allMemberIds), $inactiveUserIds)
            );

            return;
        }

        $usersWithBlock = TrainingProgramBlock::query()
            ->where('group_id', $this->groupId)
            ->where('type', $existingBlock->type)
            ->where('start', $existingBlock->start)
            ->where('note', $existingBlock->note)
            ->whereIn('user_id', $allMemberIds)
            ->pluck('user_id')
            ->all();

        $this->selectedMembers = array_map('strval', $usersWithBlock);
    }

    public function submit(): void
    {
        $this->validate([
            ...$this->buildValidationRulesFromFieldsets(),
        ], [
            'required' => __('This field is required.'),
        ]);

        Flux::modal($this->name)->close();

        $allMemberIds = array_map('strval', array_column($this->members, 'id'));
        $deselectedMembers = array_values(array_diff($allMemberIds, $this->selectedMembers));

        $this->dispatch("{$this->name}.submitted", data: [
            ...$this->data,
            'selected_members' => array_map('intval', $this->selectedMembers),
            'deselected_members' => array_map('intval', $deselectedMembers),
            'editing_block_id' => $this->editingBlockId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
            'categoryId' => $this->categoryId,
            'parentId' => $this->parentBlockId,
        ]);
    }

    public function deleteBlock(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.deleted", data: [
            'editing_block_id' => $this->editingBlockId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
            'parentId' => $this->parentBlockId,
        ]);
    }

    public function resetToParentDefaults(): void
    {
        if ($this->parentBlockId === null) {
            return;
        }

        $parentBlock = TrainingProgramBlock::find($this->parentBlockId);
        if (! $parentBlock) {
            return;
        }

        $this->data = [
            'type' => $parentBlock->type->value,
            'start' => $parentBlock->start->format('Y-m-d'),
            'end' => $parentBlock->end?->format('Y-m-d') ?? '',
            'note' => $parentBlock->note,
            'color' => $parentBlock->color,
        ];

        if ($parentBlock->config) {
            $this->data['config'] = $parentBlock->config->toArray();
        }

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;
    }

    #[Computed]
    public function formConfig(): Form
    {
        if ($this->categoryId !== null) {
            $categoryOptions = collect($this->categoryOptions)
                ->mapWithKeys(fn (array $opt, int $id) => [$id => $opt['name']])
                ->all();

            return CategoryBlockFormData::getForm([
                'type' => 'category',
                'categorySlug' => $this->categorySlug,
                'categoryOptions' => $categoryOptions,
            ]);
        }

        return NoteBlockFormData::getForm([
            'type' => $this->data['type'] ?? 'focus',
        ]);
    }

    #[Computed]
    public function fieldsets(): array
    {
        $fieldsets = parent::fieldsets();

        if (count($this->members) > 0) {
            $fieldsets[] = FormFieldset::make('athletes')
                ->label(__('Athletes'))
                ->view('livewire.training.partials.block-form-athletes')
                ->viewData(['members' => $this->members]);
        }

        return $fieldsets;
    }

    public function render(): View
    {
        return view('livewire.training.block-form');
    }
}
