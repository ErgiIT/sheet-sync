<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-medium tracking-wide text-slate-500 uppercase">
            <tr>
                <th class="px-4 py-3">Started</th>
                <th class="px-4 py-3">Spreadsheet</th>
                <th class="px-4 py-3">Trigger</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Rows read</th>
                <th class="px-4 py-3 text-right">Rows written</th>
                <th class="px-4 py-3">Duration</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($runs as $run)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><a href="{{ route('runs.show', $run) }}" class="text-indigo-600 hover:underline">{{ $run->started_at->toDateTimeString() }}</a></td>
                    <td class="px-4 py-3">{{ $run->spreadsheet?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $run->trigger->value }}</td>
                    <td class="px-4 py-3"><x-status-badge :status="$run->status" /></td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($run->rows_read) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($run->rows_written) }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $run->durationForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
