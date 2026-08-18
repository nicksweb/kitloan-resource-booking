<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm text-center">
        <x-laptop-icon class="mx-auto w-12 h-12 text-indigo-600" />
        <h1 class="mt-4 text-xl font-semibold tracking-tight text-gray-900">{{ config('app.name') }}</h1>
    </div>
    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
        {{ $slot }}
    </div>
</div>
</body>
</html>
