<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Spreadsheet;
use App\Support\GoogleSpreadsheetId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpreadsheetRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Spreadsheet|null $spreadsheet */
        $spreadsheet = $this->route('spreadsheet');

        return [
            'name' => ['required', 'string', 'max:255'],
            'google_spreadsheet_id' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9_-]{20,}$/',
                Rule::unique('spreadsheets')->ignore($spreadsheet?->id),
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'google_spreadsheet_id.regex' => 'Paste the spreadsheet URL or its id (the part after /d/ in the URL).',
            'google_spreadsheet_id.unique' => 'This spreadsheet is already configured.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'google_spreadsheet_id' => GoogleSpreadsheetId::parse((string) $this->input('google_spreadsheet_id')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
