@props(['variant' => 'primary', 'href' => null])
@php
    $classes = 'inline-flex items-center justify-center gap-1 rounded-md px-3 py-2 text-sm font-medium shadow-sm transition disabled:opacity-50 ' . match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'danger' => 'border border-red-200 bg-white text-red-600 hover:bg-red-50',
        default => 'bg-indigo-600 text-white hover:bg-indigo-500',
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($classes) }}>{{ $slot }}</button>
@endif
