@props([
    'position' => 'below',
])

@php
    $popoverClass = $position === 'left'
        ? 'right-full top-1/2 mr-2 -translate-y-1/2'
        : 'right-0 top-full mt-2';
@endphp

<div {{ $attributes->class(['relative']) }} x-data="{ legendOpen: false }">
    <flux:button variant="ghost" size="sm" icon="information-circle" x-on:click="legendOpen = !legendOpen">
        {{ __('Legend') }}
    </flux:button>
    <div x-show="legendOpen"
         x-cloak
         x-on:click.outside="legendOpen = false"
         class="absolute {{ $popoverClass }} z-20 w-56 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-2 text-sm text-zinc-700 dark:text-zinc-200">
            @foreach (\App\Models\Training\TrainingProgramSlotStatusEnum::cases() as $status)
                @php($statusColor = $status->barColor())
                <div class="flex items-center gap-2">
                    <span
                        class="status-dot h-2.5 w-2.5 rounded-full"
                        style="--status-bar-light: {{ $statusColor['light'] }}; --status-bar-dark: {{ $statusColor['dark'] }};"
                    ></span>
                    <span>{{ __($status->label()) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
