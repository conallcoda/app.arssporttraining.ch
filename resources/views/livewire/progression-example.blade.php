<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Progression Example</h1>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 text-sm md:grid-cols-2">
        <div>
            <h3 class="text-lg mb-2 font-semibold">Simplified Exercise Database</h3>
            <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Modifier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exercises as $ex)
                        <tr>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $ex->id }}</td>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $ex->name }}</td>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $ex->modifier }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            <h3 class="text-lg mb-2 font-semibold">Simplified Athlete Database</h3>
            <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Reps)</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Weight)</th>
                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">1RM</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($athletes as $ath)
                        <tr>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $ath->id }}</td>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $ath->name }}</td>
                            @foreach ($ath->tests as $test)
                                <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $test->reps }}
                                </td>
                                <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">{{ $test->weight }} kg
                                </td>
                                <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                    {{ number_format($test->oneRepMax, 1) }} kg</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mb-6">
        <h3 class="mb-2 text-lg font-semibold">Configuration</h3>
        <div class="flex items-end gap-4">
            <flux:select wire:model.live="selectedAthleteId" label="Athlete">
                @foreach ($athletes as $ath)
                    <flux:select.option value="{{ $ath->id }}">{{ $ath->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="selectedExerciseId" label="Exercise">
                @foreach ($exercises as $ex)
                    <flux:select.option value="{{ $ex->id }}">{{ $ex->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live="targetGoal" type="number" label="Target Goal (%)" step="0.1" />

            <flux:input wire:model.live="startingReps" type="number" label="Starting Reps" step="1"
                min="6" />

            <flux:select wire:model.live="selectedStrategy" label="Strategy">
                @foreach ($this->getStrategies() as $key => $class)
                    <flux:select.option value="{{ $key }}">{{ str_replace('_', ' ', ucwords($key, '_')) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 rounded border border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="font-medium">Starting 1RM:</span>
                    <span>{{ number_format($config->startingOneRepMax, 1) }} kg</span>
                </div>
                <div>
                    <span class="font-medium">Target 1RM:</span>
                    <span>{{ number_format($config->targetOneRepMax, 1) }} kg</span>
                </div>
                <div>
                    <span class="font-medium">Target:</span>
                    <span>{{ $config->target }}%</span>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-4 text-lg font-semibold">{{ $this->manager->exercise()->name }} Training Plan for
        {{ $this->manager->athlete()->name }} ({{ str_replace('_', ' ', ucwords($selectedStrategy, '_')) }} Strategy)
    </h2>

    <div class="flex flex-wrap gap-4">
        @foreach ($this->manager->results as $index => $result)
            <x-exercise-block-grid :block="$result->current" :title="'Step ' . ($index + 1) . ': ' . $result->title()" />
        @endforeach
    </div>
</div>
