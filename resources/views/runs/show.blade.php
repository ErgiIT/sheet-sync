<x-layout title="Sync run #{{ $run->id }}">
    <a href="{{ route('runs.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Sync history</a>
    <div class="mt-2 mb-6 flex items-center gap-3">
        <h1 class="text-xl font-semibold">Sync run #{{ $run->id }}</h1>
        <x-status-badge :status="$run->status" />
    </div>

    <dl class="grid grid-cols-2 gap-4 rounded-lg border border-slate-200 bg-white p-4 text-sm sm:grid-cols-4">
        <div><dt class="text-slate-500">Spreadsheet</dt><dd class="font-medium">@if ($run->spreadsheet)<a href="{{ route('spreadsheets.show', $run->spreadsheet) }}" class="text-indigo-600 hover:underline">{{ $run->spreadsheet->name }}</a>@else — @endif</dd></div>
        <div><dt class="text-slate-500">Trigger</dt><dd class="font-medium">{{ $run->trigger->value }}</dd></div>
        <div><dt class="text-slate-500">Started</dt><dd class="font-medium">{{ $run->started_at->toDateTimeString() }}</dd></div>
        <div><dt class="text-slate-500">Duration</dt><dd class="font-medium">{{ $run->durationForHumans() ?? 'still running' }}</dd></div>
        <div><dt class="text-slate-500">Rows read</dt><dd class="font-medium tabular-nums">{{ number_format($run->rows_read) }}</dd></div>
        <div><dt class="text-slate-500">Rows written</dt><dd class="font-medium tabular-nums">{{ number_format($run->rows_written) }}</dd></div>
    </dl>

    <h2 class="mt-8 mb-3 font-semibold">Messages</h2>
    @if (empty($run->messages))
        <p class="text-sm text-slate-500">Nothing to report.</p>
    @else
        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white text-sm">
            @foreach ($run->messages as $message)
                <li class="px-4 py-2 font-mono text-xs">{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</x-layout>
