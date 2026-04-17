@props(['active'])

<div class="athlete-toolbar sticky top-0 z-20 md:mt-20 bg-zinc-100/95 px-0 py-0 backdrop-blur dark:bg-zinc-800/95">
    <div class="w-full md:mx-auto md:max-w-[900px] border-b border-zinc-200 px-0 dark:border-zinc-700">
        <div class="flex flex-col gap-0">
            <x-athlete.page-tabs.row>
                <x-athlete.segmented-tabs :tabs="[
                    [
                        'name' => 'record',
                        'label' => 'Train',
                        'icon' => 'dumbbell',
                        'href' => route('athlete.dashboard'),
                        'navigate' => true,
                        'selected' => $active === 'record',
                    ],
                    [
                        'name' => 'calendar',
                        'label' => 'Calendar',
                        'icon' => 'calendar',
                        'href' => route('athlete.dashboard.calendar'),
                        'navigate' => true,
                        'selected' => $active === 'calendar',
                    ],
                ]" />
            </x-athlete.page-tabs.row>

            @if (trim((string) $slot) !== '')
                {{ $slot }}
            @endif
        </div>
    </div>
</div>
