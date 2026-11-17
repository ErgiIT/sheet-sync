<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpreadsheetTabRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var SpreadsheetTab|null $tab */
        $tab = $this->route('tab');
        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $this->route('spreadsheet') ?? $tab->spreadsheet;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('spreadsheet_tabs')->where('spreadsheet_id', $spreadsheet->id)->ignore($tab?->id),
            ],
            'header_row' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'header_row' => $this->input('header_row', 1),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
