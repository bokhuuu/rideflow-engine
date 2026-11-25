<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SGScooter</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-950 text-white min-h-screen">

    {{-- Language switcher --}}
    <div class="flex justify-end gap-3 p-4">
        <a href="?lang=ka" class="text-xl {{ app()->getLocale() === 'ka' ? 'opacity-100' : 'opacity-40' }}">🇬🇪</a>
        <a href="?lang=en" class="text-xl {{ app()->getLocale() === 'en' ? 'opacity-100' : 'opacity-40' }}">🇬🇧</a>
        <a href="?lang=ru" class="text-xl {{ app()->getLocale() === 'ru' ? 'opacity-100' : 'opacity-40' }}">🇷🇺</a>
        <a href="?lang=hy" class="text-xl {{ app()->getLocale() === 'hy' ? 'opacity-100' : 'opacity-40' }}">🇦🇲</a>
    </div>

    {{-- Page content --}}
    <main class="max-w-md mx-auto px-6 pb-12">
        @yield('content')
    </main>

</body>

</html>
