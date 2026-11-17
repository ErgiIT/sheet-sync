<x-layout :title="$spreadsheet->exists ? 'Edit spreadsheet' : 'Add spreadsheet'">
    <div class="mx-auto max-w-2xl">
        <a href="{{ $spreadsheet->exists ? route('spreadsheets.show', $spreadsheet) : route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        <h1 class="mt-2 mb-6 text-xl font-semibold">{{ $spreadsheet->exists ? "Edit {$spreadsheet->name}" : 'Add spreadsheet' }}</h1>

        <form method="POST" action="{{ $spreadsheet->exists ? route('spreadsheets.update', $spreadsheet) : route('spreadsheets.store') }}" class="card space-y-5">
            @csrf
            @if ($spreadsheet->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="label">Name</label>
                <input id="name" name="name" value="{{ old('name', $spreadsheet->name) }}" required class="input" placeholder="Sales EU">
                <x-field-error name="name" />
            </div>

            <div>
                <label for="google_spreadsheet_id" class="label">Google Sheets URL or id</label>
                <input id="google_spreadsheet_id" name="google_spreadsheet_id" value="{{ old('google_spreadsheet_id', $spreadsheet->google_spreadsheet_id) }}" required class="input font-mono"
                       placeholder="https://docs.google.com/spreadsheets/d/…/edit">
                <p class="mt-1 text-xs text-slate-500">Share the sheet with the service account email as a Viewer, or make it readable by link when using an API key.</p>
                <x-field-error name="google_spreadsheet_id" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $spreadsheet->is_active))>
                Include in the daily sync
            </label>

            <div class="flex justify-end">
                <x-button>{{ $spreadsheet->exists ? 'Save' : 'Add spreadsheet' }}</x-button>
            </div>
        </form>
    </div>
</x-layout>
