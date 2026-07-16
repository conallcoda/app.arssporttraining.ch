@props([
    'paginator' => null,
])

@if ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
    <flux:pagination :paginator="$paginator" />
@endif
