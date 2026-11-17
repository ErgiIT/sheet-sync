@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\SyncStatus::SUCCESS => 'bg-emerald-100 text-emerald-800',
        \App\Enums\SyncStatus::PARTIAL => 'bg-amber-100 text-amber-800',
        \App\Enums\SyncStatus::FAILED => 'bg-red-100 text-red-800',
        \App\Enums\SyncStatus::RUNNING => 'bg-sky-100 text-sky-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', $classes]) }}>{{ ucfirst($status->value) }}</span>
