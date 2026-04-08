<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? __('Athlete') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <script>
        if (!localStorage.getItem('flux.appearance')) {
            localStorage.setItem('flux.appearance', 'dark');
        }
    </script>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (request()->has('_preview'))
        <script>
            document.addEventListener('click', function (e) {
                const link = e.target.closest('a[href]');
                if (!link) return;
                const url = new URL(link.href, window.location.origin);
                if (url.origin === window.location.origin && !url.searchParams.has('_preview')) {
                    e.preventDefault();
                    url.searchParams.set('_preview', '1');
                    window.location.href = url.toString();
                }
            });

        </script>
    @endif
</head>

<body class="min-h-screen bg-zinc-100 dark:bg-zinc-800 antialiased">
    <flux:header class="bg-white lg:bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar class="w-full">
            <flux:brand href="/dashboard" name="{{ config('cms.name') ?? config('app.name', 'Athlete') }}" class="max-lg:hidden" />

            <flux:spacer />

            @if (! request()->has('_preview'))
                @if (config('cms.user_switching'))
                    <livewire:cms.user-switcher />
                @endif

                @if (config('cms.mobile_preview'))
                    <x-cms::mobile-preview-toggle />
                @endif
            @endif

            <x-cms::theme-toggle />

            <x-cms::profile-dropdown />
        </flux:navbar>
    </flux:header>

    <flux:modal name="change-password" flyout>
        <livewire:auth.change-password />
    </flux:modal>

    <flux:main class="w-full !p-2 !pb-20 sm:!p-6 sm:!pb-6 lg:!p-8 lg:!pb-8">
        {{ $slot }}
    </flux:main>

    @persist('athlete-layout')
        <livewire:athlete.athlete-layout />
    @endpersist

    <flux:toast />
    @fluxScripts
</body>

</html>
