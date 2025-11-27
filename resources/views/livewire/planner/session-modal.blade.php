<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'showSessionModal' => false,
    'weekUuid' => null,
    'sessionUuid' => null,
    'day' => 0,
    'slot' => 0,
    'name' => null,
    'category' => null,
    'exercises' => [],
    'mode' => 'new',
    'availableSessions' => [],
    'linkedSessionUuid' => null,
]);

$categories = computed(fn() => TrainingSessionCategory::all());

$exerciseOptions = computed(fn() => Exercise::orderBy('name')->get());

$daysFull = computed(fn() => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

$slots = computed(fn() => ['Morning', 'Afternoon']);

on([
    'open-session-modal' => function (array $data) {
        $this->weekUuid = $data['weekUuid'];
        $this->day = $data['day'];
        $this->slot = $data['slot'];
        $this->sessionUuid = $data['sessionUuid'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->category = $data['category'] ?? $this->categories->first()?->id;
        $this->exercises = $data['exercises'] ?? [];
        $this->availableSessions = $data['availableSessions'] ?? [];
        $this->linkedSessionUuid = $data['linkedTo'] ?? ($this->availableSessions[0]['uuid'] ?? null);
        $this->mode = ($data['linkedTo'] ?? null) ? 'link' : 'new';
        $this->showSessionModal = true;
    },
]);

$close = function () {
    $this->showSessionModal = false;
    $this->weekUuid = null;
    $this->sessionUuid = null;
    $this->day = 0;
    $this->slot = 0;
    $this->name = null;
    $this->category = null;
    $this->exercises = [];
    $this->mode = 'new';
    $this->availableSessions = [];
    $this->linkedSessionUuid = null;
};

$addExercise = function () {
    $this->exercises[] = null;
};

$removeExercise = function (int $index) {
    unset($this->exercises[$index]);
    $this->exercises = array_values($this->exercises);
};

$moveExerciseUp = function (int $index) {
    if ($index > 0 && isset($this->exercises[$index]) && isset($this->exercises[$index - 1])) {
        $temp = $this->exercises[$index - 1];
        $this->exercises[$index - 1] = $this->exercises[$index];
        $this->exercises[$index] = $temp;
    }
};

$moveExerciseDown = function (int $index) {
    if ($index < count($this->exercises) - 1 && isset($this->exercises[$index]) && isset($this->exercises[$index + 1])) {
        $temp = $this->exercises[$index + 1];
        $this->exercises[$index + 1] = $this->exercises[$index];
        $this->exercises[$index] = $temp;
    }
};

$save = function () {
    if ($this->mode === 'link') {
        if (empty($this->linkedSessionUuid)) {
            $this->addError('linkedSessionUuid', 'Please select a session to link.');
            return;
        }

        $this->dispatch('session-linked', [
            'weekUuid' => $this->weekUuid,
            'day' => $this->day,
            'slot' => $this->slot,
            'linkedSessionUuid' => $this->linkedSessionUuid,
        ]);
    } else {
        $this->validate([
            'category' => 'required|integer',
            'exercises.*' => 'nullable|integer|exists:exercises,id',
            'name' => 'nullable|string|max:255',
        ]);

        $filteredExercises = array_values(array_filter($this->exercises, fn($id) => $id !== null));

        $this->dispatch('session-saved', [
            'weekUuid' => $this->weekUuid,
            'sessionUuid' => $this->sessionUuid,
            'day' => $this->day,
            'slot' => $this->slot,
            'name' => $this->name,
            'category' => (int) $this->category,
            'exercises' => $filteredExercises,
        ]);
    }

    $this->close();
};

$delete = function () {
    if ($this->sessionUuid && $this->weekUuid) {
        $this->dispatch('session-deleted', [
            'weekUuid' => $this->weekUuid,
            'sessionUuid' => $this->sessionUuid,
            'day' => $this->day,
            'slot' => $this->slot,
        ]);
    }
    $this->close();
};

?>

<div>
    <flux:modal name="session-modal" wire:model="showSessionModal" variant="flyout" class="w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $sessionUuid ? 'Edit Session' : 'Add Session' }}
                </flux:heading>
                <flux:subheading>
                    {{ $this->daysFull[$day] }} {{ $this->slots[$slot] }}
                </flux:subheading>
            </div>

            @if (!$sessionUuid && count($availableSessions) > 0)
                <flux:button.group class="w-full">
                    <flux:button
                        type="button"
                        wire:click="$set('mode', 'new')"
                        :variant="$mode === 'new' ? 'primary' : 'ghost'"
                        class="flex-1"
                    >
                        New Session
                    </flux:button>
                    <flux:button
                        type="button"
                        wire:click="$set('mode', 'link')"
                        :variant="$mode === 'link' ? 'primary' : 'ghost'"
                        class="flex-1"
                    >
                        Link Session
                    </flux:button>
                </flux:button.group>
            @endif

            @if ($mode === 'link')
                <flux:field>
                    <flux:label>{{ $sessionUuid ? 'Linked To' : 'Select Session to Link' }}</flux:label>
                    <select wire:model="linkedSessionUuid" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm" {{ $sessionUuid ? 'disabled' : '' }}>
                        @foreach ($availableSessions as $session)
                            <option value="{{ $session['uuid'] }}">
                                {{ $session['name'] ?? 'Unnamed Session' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($sessionUuid)
                        <flux:description>This session is linked and inherits its content from the source session.</flux:description>
                    @endif
                    <flux:error name="linkedSessionUuid" />
                </flux:field>
            @else
                <flux:input wire:model="name" label="Session Name" />

            <flux:field>
                <flux:label>Session Category</flux:label>
                <flux:select wire:model="category" placeholder="Select a category">
                    @foreach ($this->categories as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="category" />
            </flux:field>

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Exercises</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addExercise"
                            icon="plus">
                            Add
                        </flux:button>
                    </div>

                    @if (count($exercises) > 0)
                        <div class="space-y-2">
                            @foreach ($exercises as $index => $exerciseId)
                                @php
                                    $isFirst = $index === 0;
                                    $isLast = $index === count($exercises) - 1;
                                @endphp
                                @php
                                    $selectedIds = collect($exercises)
                                        ->filter(fn($id, $i) => $id !== null && $i !== $index)
                                        ->values()
                                        ->map(fn($id) => (int) $id)
                                        ->toArray();
                                @endphp
                                <div class="flex items-center gap-2" wire:key="exercise-{{ $index }}-{{ $exerciseId }}">
                                    <div class="flex-1">
                                        <flux:select wire:model="exercises.{{ $index }}"
                                            placeholder="Select an exercise" size="sm">
                                            <flux:select.option value="">Select an exercise</flux:select.option>
                                            @foreach ($this->exerciseOptions as $exercise)
                                                @if (!in_array($exercise->id, $selectedIds))
                                                    <flux:select.option value="{{ $exercise->id }}">{{ $exercise->name }}
                                                    </flux:select.option>
                                                @endif
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="flex gap-0.5">
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveExerciseUp({{ $index }})" :disabled="$isFirst">
                                            <x-lucide-chevron-up class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveExerciseDown({{ $index }})" :disabled="$isLast">
                                            <x-lucide-chevron-down class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="removeExercise({{ $index }})">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">No exercises added yet.</p>
                    @endif
                </div>
            </flux:field>
            @endif

            <div class="flex gap-2 justify-between pt-4">
                <div>
                    @if ($sessionUuid)
                        <flux:button type="button" variant="danger" size="sm" wire:click="delete"
                            wire:confirm="Are you sure you want to delete this session?">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    @if ($sessionUuid && $mode === 'link')
                        <flux:button type="button" variant="ghost" wire:click="close">Close</flux:button>
                    @else
                        <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    @endif
                </div>
            </div>
        </form>
    </flux:modal>
</div>
