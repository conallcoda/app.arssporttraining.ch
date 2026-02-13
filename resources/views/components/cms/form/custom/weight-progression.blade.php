@php
    use App\Training\Reference\OneRepMaxConversion;

    $reps = $this->measuredReps;
    $weight = $this->measuredWeight;
    $goal = $this->targetGoal;
    $modifier = $this->data['weight']['oneRepMaxModifier'] ?? 100;

    $starting1RM = ($reps && $weight && $weight > 0 && $reps >= 1)
        ? OneRepMaxConversion::estimatedOneRepMax($reps, $weight, $modifier)
        : null;

    $target1RM = ($starting1RM !== null && $goal !== null)
        ? OneRepMaxConversion::targetOneRepMax($starting1RM, $goal)
        : null;
@endphp

<div class="grid grid-cols-[8rem_8rem_6rem] gap-x-3 gap-y-3 items-end">
    <flux:field>
        <flux:label>Measured Reps</flux:label>
        <flux:input.group>
            <flux:input wire:model.live.debounce.500ms="measuredReps" type="number"
                min="1" max="15" step="1" />
            <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
        </flux:input.group>
    </flux:field>

    <flux:field>
        <flux:label>Measured Weight</flux:label>
        <flux:input.group>
            <flux:input wire:model.live.debounce.500ms="measuredWeight" type="number"
                min="0" step="0.5" />
            <flux:input.group.suffix>kg</flux:input.group.suffix>
        </flux:input.group>
    </flux:field>

    <flux:field>
        <flux:label>Starting 1RM</flux:label>
        <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium text-sm">
            {{ $starting1RM !== null ? $starting1RM . 'kg' : 'N/A' }}
        </div>
    </flux:field>

    <flux:field class="col-span-2">
        <flux:label>Target Goal</flux:label>
        <flux:input.group>
            <flux:input wire:model.live.debounce.500ms="targetGoal" type="number"
                min="0" max="999" step="1" />
            <flux:input.group.suffix>%</flux:input.group.suffix>
        </flux:input.group>
    </flux:field>

    <flux:field>
        <flux:label>Target 1RM</flux:label>
        <div class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium text-sm">
            {{ $target1RM !== null ? $target1RM . 'kg' : 'N/A' }}
        </div>
    </flux:field>
</div>
