@blaze(fold: true)

@aware([ 'placeholder' ])

@props([
    'placeholder' => null,
    'clearable' => null,
    'invalid' => false,
    'size' => null,
])

@php

$classes = Flux::classes()
    ->add('group/select-button cursor-default py-2')
    ->add('overflow-hidden')
    ->add('flex items-center')
    ->add('shadow-xs')
    ->add('bg-white dark:bg-white/10 dark:disabled:bg-white/[7%]')
    ->add('disabled:shadow-none')
    ->add(match ($size) {
        default => 'h-10 text-base sm:text-sm rounded-lg px-3 block w-full',
        'sm' => 'h-8 text-sm rounded-md ps-3 pe-2 block w-full',
        'xs' => 'h-6 text-xs rounded-md ps-3 pe-2 block w-full',
    })
    ->add($invalid
        ? 'border border-red-500'
        : 'border border-zinc-200 border-b-zinc-300/80 dark:border-white/10'
    )
    ;
@endphp

<button
    type="button"
    {{ $attributes->class($classes) }}
    @if ($invalid) data-invalid @endif
    data-flux-group-target
    data-flux-date-picker-button
    x-data="{
        value: '',
        isSingleDate(value) {
            return /^\\d{4}-\\d{2}-\\d{2}$/.test(value ?? '')
        },
        formattedDate(value) {
            if (! this.isSingleDate(value)) return value ?? ''

            const [year, month, day] = value.split('-')

            return `${day}.${month}.${year}`
        },
    }"
    x-init="
        const picker = $el.closest('ui-date-picker')
        const sync = () => value = picker?.getAttribute('value') || ''

        sync()

        if (! picker) return

        const observer = new MutationObserver(sync)
        observer.observe(picker, { attributes: true, attributeFilter: ['value'] })

        return () => observer.disconnect()
    "
>
    <flux:icon.calendar variant="mini" class="me-2 text-zinc-400/75 [[disabled]_&]:text-zinc-200! dark:text-white/60 dark:[[disabled]_&]:text-white/40!" />

    <?php if ($slot->isNotEmpty()): ?>
        {{ $slot }}
    <?php else: ?>
        <span
            x-cloak
            x-show="isSingleDate(value)"
            x-text="formattedDate(value)"
            class="truncate flex-1 text-start text-zinc-700 [[disabled]_&]:text-zinc-500 dark:text-zinc-300 dark:[[disabled]_&]:text-zinc-400"
        ></span>

        <div x-cloak x-show="! isSingleDate(value)" class="min-w-0 flex-1">
            <flux:date-picker.selected :$placeholder />
        </div>
    <?php endif; ?>

    <?php if ($clearable): ?>
        <flux:button as="div"
            class="cursor-pointer ms-2 {{ $size === 'sm' || $size === 'xs' ? '-me-1' : '-me-2' }} [[data-flux-date-picker-button]:has([data-flux-date-picker-placeholder])_&]:hidden [[data-flux-date-picker][disabled]_&]:hidden"
            variant="subtle"
            :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
            square
            tabindex="-1"
            aria-label="Clear date"
            x-on:click.prevent.stop="$el.closest('ui-date-picker').clear()"
        >
            <flux:icon.x-mark variant="micro" />
        </flux:button>
    <?php endif; ?>

    <flux:icon.chevron-down variant="mini" class="ms-2 -me-1 text-zinc-400/75 [[data-flux-date-picker-button]:hover_&]:text-zinc-800 [[disabled]_&]:text-zinc-200! dark:text-white/60 dark:[[data-flux-date-picker-button]:hover_&]:text-white dark:[[disabled]_&]:text-white/40!" />
</button>
