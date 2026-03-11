<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? __('Athlete Training') }}</title>
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
        <div class="mb-8 flex justify-center">
            <img src="{{ asset('images/logo.webp') }}" alt="{{ __('Athlete Training') }}" class="w-12" />
        </div>

        <flux:card>
            {{ $slot }}
        </flux:card>
    </div>

    <flux:toast />
    @fluxScripts
</body>

</html>
