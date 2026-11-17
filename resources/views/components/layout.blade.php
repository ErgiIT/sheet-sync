@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? "{$title} · " : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
    @auth
        <nav class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
                <a href="{{ route('dashboard') }}" class="font-semibold text-slate-900">{{ config('app.name') }}</a>
                <div class="flex gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" @class(['font-medium text-indigo-600' => request()->routeIs('dashboard', 'spreadsheets.*', 'tabs.*', 'mappings.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('dashboard', 'spreadsheets.*', 'tabs.*', 'mappings.*')])>Spreadsheets</a>
                    <a href="{{ route('runs.index') }}" @class(['font-medium text-indigo-600' => request()->routeIs('runs.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('runs.*')])>Sync history</a>
                    <a href="{{ route('configuration.index') }}" @class(['font-medium text-indigo-600' => request()->routeIs('configuration.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('configuration.*')])>Configuration</a>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button class="text-sm text-slate-500 hover:text-slate-900">Log out</button>
                </form>
            </div>
        </nav>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
