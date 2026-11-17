<x-layout :title="$tab->name">
    <a href="{{ route('spreadsheets.show', $tab->spreadsheet) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $tab->spreadsheet->name }}</a>
    <div class="mt-2 mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tab “{{ $tab->name }}”</h1>
        <x-button :href="route('mappings.create', $tab)">Map to a table</x-button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <h2 class="font-semibold">Mappings</h2>
            @forelse ($tab->mappings as $mapping)
                <div class="card">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-medium">{{ $mapping->target_table }}</span>
                            <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $mapping->sync_mode->value }}@if ($mapping->keyColumns()) on {{ implode(', ', $mapping->keyColumns()) }}@endif</span>
                            @unless ($mapping->is_active)
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">paused</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('mappings.edit', $mapping) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('mappings.destroy', $mapping) }}" onsubmit="return confirm('Remove this mapping? The table and its data are kept.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">Remove</button>
                            </form>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @foreach ($mapping->columns as $column)
                            <span class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
                                {{ $column->source_column }} <span class="text-slate-400">→</span> <span class="font-mono">{{ $column->target_column }}</span>
                                <span class="text-slate-400">{{ $column->type->value }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card text-center text-sm text-slate-500">
                    This tab is not mapped yet. <a href="{{ route('mappings.create', $tab) }}" class="text-indigo-600 hover:underline">Map it to a table</a>.
                </div>
            @endforelse
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('tabs.update', $tab) }}" class="card space-y-4">
                @csrf
                @method('PUT')
                <h2 class="font-semibold">Settings</h2>
                <div>
                    <label for="name" class="label">Tab name</label>
                    <input id="name" name="name" value="{{ old('name', $tab->name) }}" required class="input">
                    <x-field-error name="name" />
                </div>
                <div>
                    <label for="header_row" class="label">Header row</label>
                    <input id="header_row" name="header_row" type="number" min="1" value="{{ old('header_row', $tab->header_row) }}" required class="input">
                    <x-field-error name="header_row" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tab->is_active))>
                    Include in the sync
                </label>
                <x-button class="w-full">Save</x-button>
            </form>

            <form method="POST" action="{{ route('tabs.destroy', $tab) }}" onsubmit="return confirm('Remove this tab and its mappings? Tables and synced data are kept.')">
                @csrf
                @method('DELETE')
                <x-button variant="danger" class="w-full">Remove tab</x-button>
            </form>
        </div>
    </div>
</x-layout>
