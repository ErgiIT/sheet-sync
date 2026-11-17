<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\Enums\ColumnType;
use App\Enums\SourceColumn;
use App\Enums\SyncMode;
use App\Exceptions\InvalidMappingException;
use App\Models\ColumnMapping;
use App\Models\TabMapping;
use App\Support\SqlIdentifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TargetTableManager
{
    private const KEY_INDEX_PREFIX = 'sync_key_';

    /**
     * Creates the target table, or adds whatever columns the mapping has gained since it was created.
     * Columns are never dropped or retyped: removing a mapping must not destroy synced data.
     *
     * @return list<string> columns added by this call
     */
    public function ensure(TabMapping $mapping): array
    {
        $this->assertValid($mapping);

        $table = $mapping->target_table;
        $columns = $mapping->columns;

        if ( ! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($table, $columns): void {
                $blueprint->id();
                $this->addSourceColumns($blueprint, $table, SourceColumn::cases());

                foreach ($columns as $column) {
                    $this->addColumn($blueprint, $column->target_column, $column->type);
                }
            });

            $added = $columns->pluck('target_column')->all();
        } else {
            $added = $this->addMissingColumns($table, $columns->all());
        }

        $this->syncKeyIndexes($mapping);

        return $added;
    }

    /**
     * @param  list<ColumnMapping>  $columns
     * @return list<string>
     */
    private function addMissingColumns(string $table, array $columns): array
    {
        $existing = array_flip(Schema::getColumnListing($table));

        $missingSource = array_values(array_filter(
            SourceColumn::cases(),
            fn (SourceColumn $column): bool => ! isset($existing[$column->value]),
        ));
        $missingMapped = array_values(array_filter(
            $columns,
            fn (ColumnMapping $column): bool => ! isset($existing[$column->target_column]),
        ));

        if ([] === $missingSource && [] === $missingMapped) {
            return [];
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $missingSource, $missingMapped): void {
            $this->addSourceColumns($blueprint, $table, $missingSource);

            foreach ($missingMapped as $column) {
                $this->addColumn($blueprint, $column->target_column, $column->type);
            }
        });

        return [
            ...array_map(fn (SourceColumn $column): string => $column->value, $missingSource),
            ...array_map(fn (ColumnMapping $column): string => $column->target_column, $missingMapped),
        ];
    }

    /**
     * @param  list<SourceColumn>  $columns
     */
    private function addSourceColumns(Blueprint $blueprint, string $table, array $columns): void
    {
        foreach ($columns as $column) {
            match ($column) {
                SourceColumn::SPREADSHEET => $blueprint->unsignedBigInteger($column->value)->nullable(),
                SourceColumn::TAB => $blueprint->string($column->value)->nullable(),
                SourceColumn::ROW => $blueprint->unsignedInteger($column->value)->nullable(),
                SourceColumn::SYNCED_AT => $blueprint->timestamp($column->value)->nullable(),
            };
        }

        if (in_array(SourceColumn::TAB, $columns, true)) {
            $blueprint->index(
                [SourceColumn::SPREADSHEET->value, SourceColumn::TAB->value],
                'sync_source_' . mb_substr(md5($table), 0, 12),
            );
        }
    }

    private function addColumn(Blueprint $blueprint, string $name, ColumnType $type): void
    {
        $definition = match ($type) {
            ColumnType::STRING => $blueprint->string($name),
            ColumnType::TEXT => $blueprint->text($name),
            ColumnType::INTEGER => $blueprint->bigInteger($name),
            ColumnType::DECIMAL => $blueprint->decimal($name, 18, 4),
            ColumnType::BOOLEAN => $blueprint->boolean($name),
            ColumnType::DATE => $blueprint->date($name),
            ColumnType::DATETIME => $blueprint->dateTime($name),
        };

        $definition->nullable();
    }

    /**
     * Upserts need a unique index on the key columns. Every active upsert mapping into this table
     * owns one; indexes left behind by a changed or removed key are dropped.
     */
    private function syncKeyIndexes(TabMapping $mapping): void
    {
        $table = $mapping->target_table;

        $wanted = TabMapping::query()
            ->where('target_table', $table)
            ->where('is_active', true)
            ->where('sync_mode', SyncMode::UPSERT->value)
            ->get()
            ->map(fn (TabMapping $upsert): array => $upsert->keyColumns())
            ->when(
                SyncMode::UPSERT === $mapping->sync_mode,
                fn ($keys) => $keys->push($mapping->keyColumns()),
            )
            ->filter()
            ->mapWithKeys(fn (array $columns): array => [$this->keyIndexName($table, $columns) => $columns]);

        $existing = collect(Schema::getIndexes($table))
            ->pluck('name')
            ->filter(fn (string $name): bool => str_starts_with($name, self::KEY_INDEX_PREFIX));

        $stale = $existing->diff($wanted->keys());
        $missing = $wanted->except($existing->all());

        if ($stale->isEmpty() && $missing->isEmpty()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($stale, $missing): void {
            foreach ($stale as $name) {
                $blueprint->dropUnique($name);
            }

            foreach ($missing as $name => $columns) {
                $blueprint->unique($columns, $name);
            }
        });
    }

    private function keyIndexName(string $table, array $columns): string
    {
        return self::KEY_INDEX_PREFIX . mb_substr(md5($table . '|' . implode(',', $columns)), 0, 16);
    }

    private function assertValid(TabMapping $mapping): void
    {
        if ( ! SqlIdentifier::isValid($mapping->target_table) || SqlIdentifier::isProtectedTable($mapping->target_table)) {
            throw new InvalidMappingException("\"{$mapping->target_table}\" is not an allowed target table.");
        }

        $types = [];

        foreach ($mapping->columns as $column) {
            if ( ! SqlIdentifier::isValid($column->target_column) || SqlIdentifier::isReservedColumn($column->target_column)) {
                throw new InvalidMappingException("\"{$column->target_column}\" is not an allowed column name.");
            }

            $types[$column->target_column] = $column->type;
        }

        if (SyncMode::UPSERT !== $mapping->sync_mode) {
            return;
        }

        if ([] === $mapping->keyColumns()) {
            throw new InvalidMappingException("Upsert into \"{$mapping->target_table}\" needs at least one key column.");
        }

        foreach ($mapping->keyColumns() as $key) {
            if ( ! isset($types[$key])) {
                throw new InvalidMappingException("Key column \"{$key}\" is not one of the mapped columns.");
            }

            if ( ! $types[$key]->isIndexable()) {
                throw new InvalidMappingException("Key column \"{$key}\" is long text and cannot be part of a unique key.");
            }
        }
    }
}
