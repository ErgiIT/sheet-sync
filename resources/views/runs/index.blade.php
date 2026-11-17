<x-layout title="Sync history">
    <h1 class="mb-6 text-xl font-semibold">Sync history</h1>

    @if ($runs->isEmpty())
        <p class="text-sm text-slate-500">No syncs have run yet.</p>
    @else
        @include('runs._table', ['runs' => $runs])
        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</x-layout>
