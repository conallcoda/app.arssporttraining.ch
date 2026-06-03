<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

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
    @if (config('logging.client_js.enabled'))
        <script src="{{ asset('client-js-logger.js') }}"></script>
    @endif
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

<body class="athlete-shell min-h-screen bg-zinc-100 antialiased dark:bg-zinc-800" data-athlete-shell>
    <flux:header class="!px-0 sm:!px-6 lg:!px-8 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar class="w-full">
            <div class="pl-4 sm:pl-0">
                <a href="/dashboard" class="inline-flex items-center">
                    <x-cms::brand-logo class="w-8" />
                </a>
            </div>

            <flux:spacer />

            @if (! request()->has('_preview'))
                @if (config('cms.user_switching'))
                    <div class="hidden sm:flex">
                        <livewire:cms.user-switcher />
                    </div>
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

    <flux:main class="athlete-shell__main w-full !p-0" data-athlete-main>
        {{ $slot }}
    </flux:main>

    @persist('athlete-layout')
        <livewire:athlete.athlete-layout />
    @endpersist

    @include('cms::components.youtube-player-modal')
    <x-athlete.exercise-gallery-modal />
    <flux:toast />
    @fluxScripts
</body>

</html>
