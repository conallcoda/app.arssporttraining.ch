@props([
    'href' => null,
    'showName' => false,
    'logoClass' => 'w-6',
])

@php
    $cmsName = config('cms.name') ?? config('app.name', 'CMS');
    $link = $href ?? config('cms.home', '/admin/dashboard');
@endphp

<a
    href="{{ $link }}"
    class="inline-flex items-center gap-3 text-zinc-950 dark:text-white"
    aria-label="{{ $cmsName }}"
>
    <x-cms::brand-logo :class="$logoClass" class="h-auto shrink-0" />

    @if ($showName)
        <span class="text-sm font-medium">{{ $cmsName }}</span>
    @endif
</a>
