<div>
    <flux:modal name="add-program" class="min-w-[22rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $this->editingProgramId ? 'Edit' : 'Add' }} Program</flux:heading>
            <form wire:submit="saveProgram" class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="programName" placeholder="Program name" autofocus />
                    <flux:error name="programName" />
                </flux:field>
                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">{{ $this->editingProgramId ? 'Save' : 'Add' }} Program</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="delete-program" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Program?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete this program and all its exercises.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteProgram">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="flex justify-end mb-6">
        <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddProgramModal">
            Add Program
        </flux:button>
    </div>

    @if (count($this->programs) === 0)
        <flux:text class="text-zinc-500">No programs yet. Click "Add Program" to create one.</flux:text>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="sortable_programs">
            @foreach ($this->programs as $program)
                <fieldset wire:key="program-{{ $program->id }}"
                    data-program-id="{{ $program->id }}"
                    class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0 transition-all"
                    @dragover.prevent="handleDragOver($event, {{ $program->id }})"
                    @dragleave="handleDragLeave($event)"
                    @drop="handleDrop($event, {{ $program->id }})">
                    <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                        <span class="cursor-grab active:cursor-grabbing select-none"
                            draggable="true"
                            @dragstart="handleDragStart($event, {{ $program->id }})"
                            @dragend="handleDragEnd($event)">{{ $program->name }}</span>
                        <flux:button variant="ghost" size="xs" icon="pencil"
                            wire:click="openEditProgramModal({{ $program->id }})" />
                        <flux:button variant="ghost" size="xs" icon="trash-2"
                            wire:click="confirmDeleteProgram({{ $program->id }})"
                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                    </legend>

                    <livewire:training.view.program-exercise-list
                        :program="$program"
                        wire:key="exercise-list-{{ $program->id }}"
                    />
                </fieldset>
            @endforeach
        </div>
    @endif
</div>
