@php
    $recordedAt = $recordedAt ?? null;
@endphp

<div class="space-y-4">
    @foreach ($sections as $section)
        <x-cms::section :title="$section['title'].' Preview'">
            <div class="grid gap-3 {{ $recordedAt ? 'sm:grid-cols-3' : 'grid-cols-2' }}">
                @if ($recordedAt)
                    <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Recorded</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $recordedAt }}</div>
                    </div>
                @endif
                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Max HR</div>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $section['maxHeartRate'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">IAT</div>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $section['anaerobicThreshold'] !== null ? $section['anaerobicThreshold'].'%' : '—' }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-800/90 text-zinc-300">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Zone</th>
                            <th class="px-3 py-2 text-left font-medium">BPM</th>
                            <th class="px-3 py-2 text-left font-medium">% of Max HR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr class="{{ $row['classes'] }}">
                                <td class="px-3 py-2 font-semibold">{{ $row['name'] }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ $row['bpm'] }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ $row['percent'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cms::section>
    @endforeach
</div>
