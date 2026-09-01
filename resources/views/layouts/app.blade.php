@php
    $user = auth()->user();
    $orgName = \App\Support\AppSettings::get('org_name', config('app.name'));
    $navGroups = \App\Support\Navigation::groups($user);
    $bottomBar = \App\Support\Navigation::bottomBar($user);
    $quickCreate = \App\Support\Navigation::quickCreate($user);
    $approvalCount = \App\Support\Navigation::approvalCount($user);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? $orgName }}</title>

    {{-- Paint in the right theme before first frame. --}}
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('os.theme') || 'system';
                var dark = pref === 'dark' || (pref === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                document.documentElement.dataset.density = localStorage.getItem('os.density') || 'comfortable';
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-svh bg-canvas text-ink" x-data="osShell()">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-raised focus:px-3 focus:py-2 focus:text-sm focus:shadow-lg">
        Skip to content
    </a>

    <div
        class="flex min-h-svh"
        x-on:keydown.window.prevent.cmd.k="$dispatch('palette:open')"
        x-on:keydown.window.prevent.ctrl.k="$dispatch('palette:open')"
        x-on:keydown.window="
            if (['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
            if ($event.key === '/') {
                const field = document.querySelector('[data-page-search]');
                $event.preventDefault();
                field ? field.focus() : $dispatch('palette:open');
            }
            if ($event.key === '?') { $event.preventDefault(); $dispatch('shortcuts:open'); }
            if ($event.key === 'j' || $event.key === 'k') {
                if ($event.metaKey || $event.ctrlKey || $event.altKey) return;
                $event.preventDefault();
                osMoveListCursor($event.key === 'j' ? 1 : -1);
            }
            if ($event.key === 'Enter' && osOpenListCursor()) $event.preventDefault();
        "
    >
        @include('partials.sidebar', ['navGroups' => $navGroups, 'orgName' => $orgName])

        <div class="flex min-w-0 flex-1 flex-col">
            @include('partials.topbar', [
                'orgName' => $orgName,
                'quickCreate' => $quickCreate,
                'approvalCount' => $approvalCount,
            ])

            <main id="main" class="flex-1 px-4 pt-6 pb-28 sm:px-6 sm:pb-12 lg:px-8">
                <div class="mx-auto w-full max-w-[1200px]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @include('partials.mobile-nav', ['navGroups' => $navGroups, 'orgName' => $orgName, 'bottomBar' => $bottomBar])
    @include('partials.toasts')
    @include('partials.shortcuts')

    <livewire:command-palette />

    @if (session('status'))
        <div x-data x-init="$dispatch('toast', { message: @js(session('status')), tone: 'success' })" class="sr-only" role="status">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div x-data x-init="$dispatch('toast', { message: @js(session('error')), tone: 'danger' })" class="sr-only" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Served by the app's own Livewire route: no third-party CDN executes here.
         If an FTP upload omits vendor/livewire/livewire/dist, repair it with
         /_ops/livewire-assets rather than pointing the page at a CDN. --}}
    @livewireScripts
</body>
</html>
