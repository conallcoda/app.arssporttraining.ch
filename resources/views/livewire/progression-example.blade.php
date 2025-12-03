<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Progression Example</h1>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <h3 class="mb-2 text-lg font-semibold">Athlete</h3>
            <table class="w-full border-collapse border border-gray-300">
                <tbody>
                    <tr class="border-b border-gray-300">
                        <td class="bg-gray-100 px-4 py-2 font-medium">ID</td>
                        <td class="px-4 py-2">{{ $athlete->id }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="bg-gray-100 px-4 py-2 font-medium">Name</td>
                        <td class="px-4 py-2">{{ $athlete->name }}</td>
                    </tr>
                    @foreach ($athlete->tests as $test)
                        <tr class="border-b border-gray-300">
                            <td class="bg-gray-100 px-4 py-2 font-medium">Test (Reps)</td>
                            <td class="px-4 py-2">{{ $test->reps }}</td>
                        </tr>
                        <tr class="border-b border-gray-300">
                            <td class="bg-gray-100 px-4 py-2 font-medium">Test (Weight)</td>
                            <td class="px-4 py-2">{{ $test->weight }} kg</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            <h3 class="mb-2 text-lg font-semibold">Exercise</h3>
            <table class="w-full border-collapse border border-gray-300">
                <tbody>
                    <tr class="border-b border-gray-300">
                        <td class="bg-gray-100 px-4 py-2 font-medium">ID</td>
                        <td class="px-4 py-2">{{ $exercise->id }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="bg-gray-100 px-4 py-2 font-medium">Name</td>
                        <td class="px-4 py-2">{{ $exercise->name }}</td>
                    </tr>
                    <tr class="border-b border-gray-300">
                        <td class="bg-gray-100 px-4 py-2 font-medium">Modifier</td>
                        <td class="px-4 py-2">{{ $exercise->modifier }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="mb-4 text-lg font-semibold">{{ $this->manager->exercise->name }}</h2>

    @foreach ($this->manager->results as $index => $result)
        <x-exercise-block-grid :block="$result->current" :title="'Step ' . ($index + 1) . ': ' . $result->title()" />
    @endforeach
</div>
