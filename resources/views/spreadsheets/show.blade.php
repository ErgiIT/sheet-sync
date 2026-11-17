<x-layout :title="$spreadsheet->name">
    <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800">← Spreadsheets</a>
    <div class="mt-2 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ $spreadsheet->name }}
                @unless ($spreadsheet->is_active)
                    <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 align-middle text-xs font-normal text-slate-500">paused</span>
                @endunless
            </h1>
            <a href="{{ $spreadsheet->googleUrl() }}" target="_blank" rel="noopener" class="font-mono text-xs text-slate-500 hover:text-indigo-600">{{ $spreadsheet->google_spreadsheet_id }} ↗</a>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('spreadsheets.sync', $spreadsheet) }}">
                @csrf
                <x-button>Sync now</x-button>
            </form>
            <x-button variant="secondary" :href="route('spreadsheets.edit', $spreadsheet)">Edit</x-button>
            <form method="POST" action="{{ route('spreadsheets.destroy', $spreadsheet) }}" onsubmit="return confirm('Remove this spreadsheet and its tabs and mappings? Tables and synced data are kept.')">
                @csrf
                @method('DELETE')
                <x-button variant="danger">Remove</x-button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="mb-3 font-semibold">Tabs</h2>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Tab</th>
                            <th class="px-4 py-3">Header row</th>
                            <th class="px-4 py-3">Mappings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($spreadsheet->tabs as $tab)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('tabs.show', $tab) }}" class="font-medium text-indigo-600 hover:underline">{{ $tab->name }}</a>
                                    @unless ($tab->is_active)
                                        <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">paused</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">{{ $tab->header_row }}</td>
                                <td class="px-4 py-3">{{ $tab->mappings_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">No tabs yet. Add the first one on the right.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('tabs.store', $spreadsheet) }}" class="card h-fit space-y-4" x-data="tabPicker(@js(route('spreadsheets.tab-names', $spreadsheet)))">
            @csrf
            <h2 class="font-semibold">Add a tab</h2>
            <div>
                <div class="flex items-center justify-between">
                    <label for="tab-name" class="label">Tab name</label>
                    <button type="button" class="text-xs text-indigo-600 hover:underline" @click="detect" :disabled="loading" x-text="loading ? 'Reading…' : 'Detect tabs'"></button>
                </div>
                <input id="tab-name" name="name" value="{{ old('name') }}" required class="input" list="tab-names" placeholder="Sales">
                <datalist id="tab-names"><template x-for="name in names" :key="name"><option :value="name"></option></template></datalist>
                <p x-cloak x-show="names.length" class="mt-1 text-xs text-slate-500">Found: <span x-text="names.join(', ')"></span></p>
                <p x-cloak x-show="error" class="mt-1 text-xs text-red-600" x-text="error"></p>
                <x-field-error name="name" />
            </div>
            <div>
                <label for="header_row" class="label">Header row</label>
                <input id="header_row" name="header_row" type="number" min="1" value="{{ old('header_row', 1) }}" required class="input">
                <p class="mt-1 text-xs text-slate-500">Rows above the header (titles, notes) are ignored.</p>
                <x-field-error name="header_row" />
            </div>
            <x-button class="w-full">Add tab</x-button>
        </form>
    </div>

    @if ($runs->isNotEmpty())
        <h2 class="mt-10 mb-3 font-semibold">Recent runs</h2>
        @include('runs._table', ['runs' => $runs])
    @endif
</x-layout>
