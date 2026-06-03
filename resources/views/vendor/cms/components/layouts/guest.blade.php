@php
    $cmsName = config('cms.name') ?? config('app.name', 'CMS');
    $cmsLogo = config('cms.logo');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ? \Coda\Cms\Livewire\CmsPage::buildTitle($title) : $cmsName }}</title>
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
</head>

<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 antialiased flex items-center justify-center">
    <div class="fixed top-4 right-4">
        <flux:button
            x-data
            variant="subtle"
            square
            aria-label="Toggle color scheme"
            x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
        >
            <flux:icon.sun x-show="$flux.dark" variant="mini" class="text-zinc-500 dark:text-white" />
            <flux:icon.moon x-show="! $flux.dark" variant="mini" class="text-zinc-500 dark:text-white" />
        </flux:button>
    </div>

    <div class="w-full max-w-md p-6">
        @if($cmsLogo)
            <div class="mb-8 flex justify-center">
                @if(is_array($cmsLogo))
                    <img x-data x-show="!$flux.dark" src="{{ asset($cmsLogo['light'] ?? $cmsLogo['dark'] ?? '') }}" alt="{{ $cmsName }}" class="w-12" />
                    <img x-data x-show="$flux.dark" src="{{ asset($cmsLogo['dark'] ?? $cmsLogo['light'] ?? '') }}" alt="{{ $cmsName }}" class="w-12" />
                @else
                    <img src="{{ asset($cmsLogo) }}" alt="{{ $cmsName }}" class="w-12" />
                @endif
            </div>
        @else
            <div class="mb-8 text-center">
                <flux:heading size="xl">{{ $cmsName }}</flux:heading>
            </div>
        @endif

        <flux:card>
            {{ $slot }}
        </flux:card>
    </div>

    <flux:toast />
    @fluxScripts
</body>

</html>
