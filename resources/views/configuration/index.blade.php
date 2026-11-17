<x-layout title="Configuration">
    <h1 class="mb-1 text-xl font-semibold">Configuration</h1>
    <p class="mb-6 text-sm text-slate-500">The same configuration the UI edits, as a file. Import adds and updates; it never deletes spreadsheets, tabs or mappings.</p>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Current configuration</h2>
                <x-button variant="secondary" :href="route('configuration.export')">Download JSON</x-button>
            </div>
            <pre class="max-h-[36rem] overflow-auto rounded-lg border border-slate-200 bg-slate-900 p-4 text-xs text-slate-100">{{ $json }}</pre>
        </div>

        <form method="POST" action="{{ route('configuration.import') }}" enctype="multipart/form-data" class="card h-fit space-y-4">
            @csrf
            <h2 class="font-semibold">Import</h2>
            <div>
                <label for="file" class="label">JSON file</label>
                <input id="file" name="file" type="file" accept="application/json,.json" required class="mt-1 block w-full text-sm">
                <x-field-error name="file" />
            </div>
            <p class="text-xs text-slate-500">Target tables are created or extended as each mapping is saved. See <code>config/sheet-sync.example.json</code> for the format.</p>
            <x-button class="w-full">Import</x-button>
        </form>
    </div>
</x-layout>
