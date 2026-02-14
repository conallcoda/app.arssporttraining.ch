<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Database' }}</title>
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

<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand href="/" name="Athlete Training">
                <x-slot name="logo">
                    <img src="{{ asset('images/logo.webp') }}" alt="Athlete Training" class="w-6" />
                </x-slot>
            </flux:sidebar.brand>
            <flux:sidebar.collapse />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group expandable icon="database" heading="Database" class="grid">
                <flux:sidebar.item icon="users" href="/athletes">Athletes</flux:sidebar.item>
                <flux:sidebar.item icon="dumbbell" href="/exercises">Exercises</flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group expandable icon="trophy" heading="Training" class="grid">
                <flux:sidebar.item icon="clipboard-list" href="/training-plans">Plans</flux:sidebar.item>
                <flux:sidebar.item icon="settings" href="/training/program-categories">Settings</flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />
    </flux:sidebar>

    <flux:header class="bg-white lg:bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar class="w-full">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            {{ $navbar ?? '' }}

            <flux:spacer />

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
        </flux:navbar>
    </flux:header>

    {{ $slot }}

    <flux:toast />
    @fluxScripts
</body>

</html>
