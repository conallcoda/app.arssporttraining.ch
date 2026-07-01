<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? __('Docs') }}</title>
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

<body class="min-h-screen bg-black antialiased">
    <div class="p-4 h-screen flex flex-col">
        {{ $slot }}
    </div>

    <flux:toast />
    @fluxScripts
</body>

</html>
