@php
    $orgName = \App\Support\AppSettings::get('org_name', config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $orgName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen text-[15px] text-ink">
    <div class="relative flex min-h-screen items-center justify-center px-4 py-12">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 left-1/2 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-accent/10 blur-3xl"></div>
        </div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $orgName }}</h1>
                <p class="mt-2 font-mono text-[11px] font-medium tracking-[0.2em] text-muted uppercase">Portfolio OS</p>
            </div>

            <div class="rounded-2xl border border-line bg-surface/90 p-7 shadow-[0_20px_50px_-30px_rgba(20,23,31,0.35)] backdrop-blur">
                {{ $slot }}
            </div>
        </div>
    </div>
    {{-- Served by the app's own Livewire route: no third-party CDN executes here. --}}
    @livewireScripts
</body>
</html>
