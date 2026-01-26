<?php

use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingNode;
use function Livewire\Volt\{state, computed, on};

state([
    'showSessionModal' => false,
    'weekUuid' => null,
    'sessionUuid' => null,
    'day' => 0,
    'slot' => 0,
    'name' => null,
    'color' => 'blue',
    'exercises' => [],
    'mode' => 'new',
    'availableSessions' => [],
    'linkedSessionUuid' => null,
]);

$exerciseOptions = computed(fn() => Exercise::orderBy('name')->get());

$daysFull = computed(fn() => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

$slots = computed(fn() => ['Morning', 'Afternoon']);

on([
    'open-session-modal' => function (array $data) {
        $this->weekUuid = $data['weekUuid'];
        $this->day = (int) ($data['day'] ?? 0);
        $this->slot = (int) ($data['slot'] ?? 0);
        $this->sessionUuid = $data['sessionUuid'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->color = $data['color'] ?? 'blue';
        $this->exercises = $data['exercises'] ?? [];
        $this->availableSessions = $data['availableSessions'] ?? [];
        $this->linkedSessionUuid = $data['linkedTo'] ?? ($this->availableSessions[0]['uuid'] ?? null);
        $this->mode = ($data['linkedTo'] ?? null) ? 'link' : 'new';
        $this->showSessionModal = true;
    },
]);

$close = function () {
    $this->reset([
        'showSessionModal',
        'weekUuid',
        'sessionUuid',
        'day',
        'slot',
        'name',
        'color',
        'exercises',
        'mode',
        'availableSessions',
        'linkedSessionUuid',
    ]);
};

$addExercise = function () {
    $this->exercises[] = null;
};

$removeExercise = function (int $index) {
    if (!is_array($this->exercises)) {
        $this->exercises = [];
        return;
    }
    unset($this->exercises[$index]);
    $this->exercises = array_values($this->exercises);
};

$moveExercise = function (int $index, int $direction) {
    if (!is_array($this->exercises)) {
        return;
    }
    $newIndex = $index + $direction;
    if ($newIndex < 0 || $newIndex >= count($this->exercises)) {
        return;
    }
    if (!isset($this->exercises[$index]) || !isset($this->exercises[$newIndex])) {
        return;
    }
    [$this->exercises[$index], $this->exercises[$newIndex]] = [$this->exercises[$newIndex], $this->exercises[$index]];
};

$moveExerciseUp = fn(int $index) => $this->moveExercise($index, -1);

$moveExerciseDown = fn(int $index) => $this->moveExercise($index, 1);

$save = function () {
    if ($this->mode === 'link') {
        if ($this->sessionUuid) {
            $this->dispatch('session-link-updated', [
                'sessionUuid' => $this->sessionUuid,
                'linkedTo' => $this->linkedSessionUuid ?: null,
            ]);
        } else {
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
        }
    } else {
        $this->validate([
            'exercises.*' => 'nullable|integer|exists:exercises,id',
            'name' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        $filteredExercises = array_values(array_filter($this->exercises, fn($id) => $id !== null));

        $this->dispatch('session-saved', [
            'weekUuid' => $this->weekUuid,
            'sessionUuid' => $this->sessionUuid,
            'day' => $this->day,
            'slot' => $this->slot,
            'name' => $this->name,
            'color' => $this->color,
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
                    {{ $this->daysFull[$day] ?? 'Monday' }} {{ $this->slots[$slot] ?? 'Morning' }}
                </flux:subheading>
            </div>

            @if (!$sessionUuid && is_array($availableSessions) && count($availableSessions) > 0)
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
                    <flux:label>Linked To</flux:label>
                    <select wire:model.live="linkedSessionUuid" class="w-full rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm">
                        <option value="" @selected($linkedSessionUuid === null || $linkedSessionUuid === '')>Unlinked</option>
                        @foreach ($availableSessions as $session)
                            <option value="{{ $session['uuid'] }}" @selected($linkedSessionUuid === $session['uuid'])>
                                {{ $session['name'] ?? 'Unnamed Session' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($sessionUuid && $linkedSessionUuid)
                        <flux:description>This session inherits its content from the source session. Select "Unlinked" to copy the current values and make it independent.</flux:description>
                    @elseif ($sessionUuid && !$linkedSessionUuid)
                        <flux:description>This session will become independent with its own values.</flux:description>
                    @endif
                    <flux:error name="linkedSessionUuid" />
                </flux:field>
            @else
                <flux:input wire:model="name" label="Session Name" />

                <x-color-select wire-model="color" label="Session Color" />

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Exercises</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addExercise"
                            icon="plus">
                            Add
                        </flux:button>
                    </div>

                    @if (is_array($exercises) && count($exercises) > 0)
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
                    <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
