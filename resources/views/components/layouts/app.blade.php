<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SiJual' }} — MSME Command Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="font-body text-on-surface bg-background antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <x-navigation.side-nav-bar :active="$activeNav ?? ''" />

        {{-- Main area --}}
        <div class="flex-1 pl-0 lg:pl-64 flex flex-col h-screen overflow-hidden">
            {{-- Top App Bar --}}
            @if(!isset($hideTopBar) || !$hideTopBar)
                <x-navigation.top-app-bar />
            @endif

            {{-- Page Content --}}
            <main class="{{ (isset($hideTopBar) && $hideTopBar) ? '' : 'pt-16' }} flex-1 flex flex-col relative bg-[#FCFBFB] overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Copilot Bar (global) --}}
    @if(isset($showCopilot) && $showCopilot)
        <x-ui.copilot-bar />
    @endif

    {{-- Mobile Bottom Nav --}}
    <x-navigation.bottom-nav-bar :active="$activeNav ?? ''" class="lg:hidden" />

    @stack('scripts')
</body>
</html>
