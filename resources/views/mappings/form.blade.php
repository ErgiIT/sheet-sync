<x-layout :title="$mapping->exists ? 'Edit mapping' : 'New mapping'">
    <a href="{{ route('tabs.show', $tab) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $tab->spreadsheet->name }} / {{ $tab->name }}</a>
    <h1 class="mt-2 mb-6 text-xl font-semibold">{{ $mapping->exists ? "Mapping to {$mapping->target_table}" : 'Map tab to a table' }}</h1>

    <form method="POST" action="{{ $mapping->exists ? route('mappings.update', $mapping) : route('mappings.store', $tab) }}"
          x-data="mappingForm(@js([
              'columns' => $columns,
              'keyColumns' => array_values($keyColumns),
              'syncMode' => old('sync_mode', $mapping->sync_mode?->value ?? 'replace'),
              'headersUrl' => route('tabs.headers', $tab),
              'types' => collect($types)->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])->all(),
          ]))"
          class="space-y-6">
        @csrf
        @if ($mapping->exists)
            @method('PUT')
        @endif

        <div class="card grid gap-5 md:grid-cols-3">
            <div>
                <label for="target_table" class="label">Target table</label>
                <input id="target_table" name="target_table" value="{{ old('target_table', $mapping->target_table) }}" required class="input font-mono" placeholder="sales">
                <p class="mt-1 text-xs text-slate-500">Created on save if it does not exist; missing columns are added.</p>
                <x-field-error name="target_table" />
            </div>
            <div>
                <label for="sync_mode" class="label">Sync mode</label>
                <select id="sync_mode" name="sync_mode" x-model="syncMode" class="input">
                    <option value="replace">Replace — mirror the tab on every sync</option>
                    <option value="upsert">Upsert — update rows by key, keep the rest</option>
                </select>
                <x-field-error name="sync_mode" />
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $mapping->is_active))>
                    Active
                </label>
            </div>
        </div>

        <div class="card">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Columns</h2>
                    <p class="text-xs text-slate-500">Matched by header name (case, spaces and dashes ignored), so column order in the sheet does not matter.</p>
                </div>
                <x-button type="button" variant="secondary" @click="loadHeaders" x-bind:disabled="loading">
                    <span x-text="loading ? 'Reading sheet…' : 'Load headers from sheet'"></span>
                </x-button>
            </div>
            <p x-cloak x-show="headerError" x-text="headerError" class="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700"></p>
            <x-field-error name="columns" />

            <div class="grid grid-cols-[1fr_1fr_12rem_2rem] gap-2 text-xs font-medium tracking-wide text-slate-500 uppercase">
                <div>Sheet header</div>
                <div>Table column</div>
                <div>Type</div>
                <div></div>
            </div>
            <template x-for="(column, index) in columns" :key="index">
                <div class="grid grid-cols-[1fr_1fr_12rem_2rem] items-start gap-2">
                    <input :name="`columns[${index}][source_column]`" x-model="column.source_column" required class="input" placeholder="Product Name">
                    <input :name="`columns[${index}][target_column]`" x-model="column.target_column" required class="input font-mono" placeholder="name">
                    <select :name="`columns[${index}][type]`" x-model="column.type" class="input">
                        <template x-for="type in types" :key="type.value">
                            <option :value="type.value" x-text="type.label" :selected="type.value === column.type"></option>
                        </template>
                    </select>
                    <button type="button" class="mt-2 text-lg leading-none text-slate-400 hover:text-red-600" @click="removeColumn(index)" x-show="columns.length > 1" title="Remove">×</button>
                </div>
            </template>
            @foreach ($errors->getMessages() as $key => $messages)
                @if (str_starts_with($key, 'columns.'))
                    <p class="mt-1 text-sm text-red-600">Row {{ (int) explode('.', $key)[1] + 1 }}: {{ $messages[0] }}</p>
                @endif
            @endforeach
            <button type="button" class="mt-3 text-sm text-indigo-600 hover:underline" @click="addColumn()">+ Add column</button>
        </div>

        <div class="card" x-cloak x-show="syncMode === 'upsert'">
            <h2 class="font-semibold">Key columns</h2>
            <p class="mb-3 text-xs text-slate-500">Rows with the same key are updated instead of inserted. A unique index is created on these columns.</p>
            <div class="flex flex-wrap gap-4">
                <template x-for="column in keyCandidates()" :key="column.target_column">
                    <label class="flex items-center gap-2 font-mono text-sm">
                        <input type="checkbox" name="key_columns[]" :value="column.target_column" x-model="keyColumns" :disabled="syncMode !== 'upsert'">
                        <span x-text="column.target_column"></span>
                    </label>
                </template>
                <p x-show="!keyCandidates().length" class="text-sm text-slate-500">Add columns first.</p>
            </div>
            <x-field-error name="key_columns" />
            @foreach ($errors->getMessages() as $key => $messages)
                @if (str_starts_with($key, 'key_columns.'))
                    <p class="mt-1 text-sm text-red-600">{{ $messages[0] }}</p>
                @endif
            @endforeach
        </div>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('tabs.show', $tab)">Cancel</x-button>
            <x-button>Save mapping</x-button>
        </div>
    </form>
</x-layout>
