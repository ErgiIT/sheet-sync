<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Services\Configuration\MappingRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TabMappingRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var TabMapping|null $mapping */
        $mapping = $this->route('mapping');
        /** @var SpreadsheetTab $tab */
        $tab = $this->route('tab') ?? $mapping->tab;

        $rules = MappingRules::rules();
        $rules['target_table'][] = Rule::unique('tab_mappings')->where('spreadsheet_tab_id', $tab->id)->ignore($mapping?->id);

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (MappingRules::crossFieldErrors($this->all()) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'target_table.unique' => 'This tab is already mapped to that table; edit the existing mapping instead.',
            'columns.required' => 'Add at least one column.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target_table' => mb_strtolower(trim((string) $this->input('target_table'))),
            'is_active' => $this->boolean('is_active'),
            'key_columns' => array_values(array_filter((array) $this->input('key_columns', []))),
            'columns' => array_values(array_map(fn (array $column): array => [
                ...$column,
                'target_column' => mb_strtolower(trim((string) ($column['target_column'] ?? ''))),
            ], (array) $this->input('columns', []))),
        ]);
    }
}
