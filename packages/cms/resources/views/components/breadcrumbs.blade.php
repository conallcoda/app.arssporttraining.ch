@php
    $resolver = app(\Coda\Cms\Navigation\BreadcrumbResolver::class);
    $crumbs = $resolver->resolve();
@endphp

@if (count($crumbs) > 1)
    <flux:breadcrumbs class="mb-4">
        @foreach ($crumbs as $crumb)
            @if ($crumb->href && ! $crumb->current)
                <flux:breadcrumbs.item :href="$crumb->href">{{ $crumb->label }}</flux:breadcrumbs.item>
            @else
                <flux:breadcrumbs.item>{{ $crumb->label }}</flux:breadcrumbs.item>
            @endif
        @endforeach
    </flux:breadcrumbs>
@endif
