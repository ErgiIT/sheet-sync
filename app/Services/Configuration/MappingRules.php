<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Enums\ColumnType;
use App\Enums\SyncMode;
use App\Rules\TargetColumnName;
use App\Rules\TargetTableName;
use Illuminate\Validation\Rule;

/**
 * One rule set for a tab mapping, shared by the UI form and the JSON config import.
 */
class MappingRules
{
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}target_table" => ['required', 'string', new TargetTableName()],
            "{$prefix}sync_mode" => ['nullable', Rule::enum(SyncMode::class)],
            "{$prefix}key_columns" => ['nullable', 'array'],
            "{$prefix}key_columns.*" => ['string', 'distinct'],
            "{$prefix}is_active" => ['boolean'],
            "{$prefix}columns" => ['required', 'array', 'min:1'],
            "{$prefix}columns.*.source_column" => ['required', 'string', 'max:255'],
            "{$prefix}columns.*.target_column" => ['required', 'string', new TargetColumnName()],
            "{$prefix}columns.*.type" => ['required', Rule::enum(ColumnType::class)],
        ];
    }

    /**
     * Cross-field checks the rule strings cannot express. `distinct` is not used for target columns
     * because under a nested wildcard it compares across every mapping in the document.
     *
     * @return array<string, string> attribute => message
     */
    public static function crossFieldErrors(array $mapping, string $prefix = ''): array
    {
        $errors = [];
        $seen = [];

        foreach ($mapping['columns'] ?? [] as $index => $column) {
            $target = $column['target_column'] ?? null;

            if ( ! is_string($target) || '' === $target) {
                continue;
            }

            if (isset($seen[$target])) {
                $errors["{$prefix}columns.{$index}.target_column"] = "Column \"{$target}\" is mapped more than once.";
            }

            $seen[$target] = true;
        }

        return [...$errors, ...self::keyColumnErrors($mapping, $prefix)];
    }

    private static function keyColumnErrors(array $mapping, string $prefix): array
    {
        if (SyncMode::UPSERT->value !== ($mapping['sync_mode'] ?? null)) {
            return [];
        }

        $keys = $mapping['key_columns'] ?? [];

        if ([] === $keys) {
            return ["{$prefix}key_columns" => 'Upsert mode needs at least one key column.'];
        }

        $types = collect($mapping['columns'] ?? [])->pluck('type', 'target_column');
        $errors = [];

        foreach ($keys as $index => $key) {
            if ( ! $types->has($key)) {
                $errors["{$prefix}key_columns.{$index}"] = "Key column \"{$key}\" must be one of the mapped target columns.";
            } elseif (ColumnType::TEXT->value === $types[$key]) {
                $errors["{$prefix}key_columns.{$index}"] = "Key column \"{$key}\" is long text and cannot be part of a unique key.";
            }
        }

        return $errors;
    }
}
