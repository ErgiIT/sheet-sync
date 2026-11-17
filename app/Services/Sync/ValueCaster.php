<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ColumnType;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Throwable;

class ValueCaster
{
    private const SHEETS_EPOCH = '1899-12-30';

    private const TRUE_VALUES = ['1', 'true', 'yes', 'y', 'x', '✓', '✔', 'on'];

    private const FALSE_VALUES = ['0', 'false', 'no', 'n', 'off', '-'];

    /**
     * @return array{0: mixed, 1: bool} the cast value, and whether a non-empty input had to be dropped
     */
    public function cast(mixed $value, ColumnType $type): array
    {
        if (null === $value || (is_string($value) && '' === trim($value))) {
            return [null, false];
        }

        $cast = match ($type) {
            ColumnType::STRING => mb_substr($this->toString($value), 0, 255),
            ColumnType::TEXT => $this->toString($value),
            ColumnType::INTEGER => $this->toInteger($value),
            ColumnType::DECIMAL => $this->toDecimal($value),
            ColumnType::BOOLEAN => $this->toBoolean($value),
            ColumnType::DATE => $this->toDate($value, config('sheets.date_formats'))?->toDateString(),
            ColumnType::DATETIME => $this->toDate($value, [...config('sheets.datetime_formats'), ...config('sheets.date_formats')])?->toDateTimeString(),
        };

        return [$cast, null === $cast];
    }

    private function toString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function toInteger(mixed $value): ?int
    {
        $number = $this->toDecimal($value);

        return null === $number ? null : (int) round((float) $number);
    }

    private function toDecimal(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $clean = preg_replace('/[^\d.,\-]/u', '', (string) $value);

        if (null === $clean || '' === $clean) {
            return null;
        }

        $lastDot = mb_strrpos($clean, '.');
        $lastComma = mb_strrpos($clean, ',');

        if (false !== $lastDot && false !== $lastComma) {
            // Whichever separator comes last is the decimal one: 1.234,56 and 1,234.56.
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $thousands = '.' === $decimal ? ',' : '.';
            $clean = str_replace([$thousands, $decimal], ['', '.'], $clean);
        } elseif (false !== $lastComma) {
            // A lone comma followed by exactly three digits is a thousands separator: 1,234.
            $clean = 1 === preg_match('/^-?\d{1,3}(,\d{3})+$/', $clean)
                ? str_replace(',', '', $clean)
                : str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? $clean : null;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match (true) {
            in_array($normalized, self::TRUE_VALUES, true) => true,
            in_array($normalized, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }

    /**
     * @param  list<string>  $formats
     */
    private function toDate(mixed $value, array $formats): ?CarbonImmutable
    {
        if (is_int($value) || is_float($value)) {
            return $this->fromSerial((float) $value);
        }

        $value = trim((string) $value);

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            // Without the warnings check 31/02/2026 silently rolls over to 3 March.
            if (false !== $date && (false === $errors || 0 === $errors['warning_count'] + $errors['error_count'])) {
                return CarbonImmutable::instance($date);
            }
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function fromSerial(float $serial): ?CarbonImmutable
    {
        if ($serial < 1 || $serial > 2958465) {
            return null;
        }

        $seconds = (int) round(($serial - floor($serial)) * 86400);

        return CarbonImmutable::parse(self::SHEETS_EPOCH)->addDays((int) floor($serial))->addSeconds($seconds);
    }
}
