<x-layout title="Spreadsheets">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Spreadsheets</h1>
            <p class="text-sm text-slate-500">Synced daily at {{ config('sheets.schedule_time') }} ({{ config('app.timezone') }}).</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('sync.all') }}">
                @csrf
                <x-button variant="secondary" :disabled="$spreadsheets->isEmpty()">Sync all now</x-button>
            </form>
            <x-button :href="route('spreadsheets.create')">Add spreadsheet</x-button>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium tracking-wide text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Tabs</th>
                    <th class="px-4 py-3">Last run</th>
                    <th class="px-4 py-3">Last successful sync</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($spreadsheets as $spreadsheet)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('spreadsheets.show', $spreadsheet) }}" class="font-medium text-indigo-600 hover:underline">{{ $spreadsheet->name }}</a>
                            @unless ($spreadsheet->is_active)
                                <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">paused</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $spreadsheet->tabs_count }}</td>
                        <td class="px-4 py-3">
                            @if ($spreadsheet->latestSyncRun)
                                <a href="{{ route('runs.show', $spreadsheet->latestSyncRun) }}"><x-status-badge :status="$spreadsheet->latestSyncRun->status" /></a>
                                <span class="ml-1 text-slate-500">{{ $spreadsheet->latestSyncRun->started_at->diffForHumans() }}</span>
                            @else
                                <span class="text-slate-400">never</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $spreadsheet->last_synced_at?->toDayDateTimeString() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('spreadsheets.sync', $spreadsheet) }}">
                                @csrf
                                <button class="text-sm text-indigo-600 hover:underline">Sync now</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                            No spreadsheets yet. <a href="{{ route('spreadsheets.create') }}" class="text-indigo-600 hover:underline">Add one</a>
                            or <a href="{{ route('configuration.index') }}" class="text-indigo-600 hover:underline">import a config file</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($recentRuns->isNotEmpty())
        <h2 class="mt-10 mb-3 font-semibold">Recent runs</h2>
        @include('runs._table', ['runs' => $recentRuns])
    @endif
</x-layout>
