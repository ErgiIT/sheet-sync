<?php

declare(strict_types=1);

namespace App\Enums;

enum ColumnType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::STRING => 'Text (255)',
            self::TEXT => 'Long text',
            self::INTEGER => 'Integer',
            self::DECIMAL => 'Decimal',
            self::BOOLEAN => 'Yes / No',
            self::DATE => 'Date',
            self::DATETIME => 'Date & time',
        };
    }

    public function isIndexable(): bool
    {
        return self::TEXT !== $this;
    }
}
