@php
    $cmsName = config('cms.name') ?? config('app.name', 'CMS');
    $cmsHome = config('cms.home', '/admin/dashboard');
    $cmsLogo = config('cms.logo');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? __('Admin') }}</title>
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
            <flux:sidebar.brand :href="$cmsHome" :name="$cmsName">
                @if($cmsLogo)
                    <x-slot name="logo">
                        @if(is_array($cmsLogo))
                            <img x-show="!$flux.dark" src="{{ asset($cmsLogo['light'] ?? $cmsLogo['dark'] ?? '') }}" alt="{{ $cmsName }}" class="w-6" />
                            <img x-show="$flux.dark" src="{{ asset($cmsLogo['dark'] ?? $cmsLogo['light'] ?? '') }}" alt="{{ $cmsName }}" class="w-6" />
                        @else
                            <img src="{{ asset($cmsLogo) }}" alt="{{ $cmsName }}" class="w-6" />
                        @endif
                    </x-slot>
                @endif
            </flux:sidebar.brand>
            <flux:sidebar.collapse />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <x-cms::sidebar-nav />
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <form method="POST" action="/logout">
                @csrf
                <flux:sidebar.item icon="log-out" type="submit">
                    {{ __('Logout') }}
                </flux:sidebar.item>
            </form>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:header class="bg-white lg:bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar class="w-full">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            {{ $navbar ?? '' }}

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

    <flux:modal name="coach-settings" flyout>
        <livewire:coach-settings />
    </flux:modal>

    {{ $slot }}

    <livewire:cms.component-portal />
    @include('cms::components.youtube-player-modal')
    <flux:toast />
    @fluxScripts
</body>

</html>
