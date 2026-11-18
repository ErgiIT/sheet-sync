<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ColumnType;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

class ValueCaster
{
    private const SHEETS_EPOCH = '1899-12-30';

    private const TRUE_VALUES = ['1', 'true', 'yes', 'y', 'x', '✓', '✔', 'on'];

    private const FALSE_VALUES = ['0', 'false', 'no', 'n', 'off', '-'];

    private const INTEGER_LIMIT = 9.2e18;

    private const DECIMAL_LIMIT = 1e14;

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

    private static function isGrouped(string $whole, string $separator): bool
    {
        return 1 === preg_match('/^(?:\d{1,3}|[1-9]\d{0,2}(?:' . preg_quote($separator, '/') . '\d{3})+)$/', $whole);
    }

    /**
     * MySQL DATE and DATETIME columns hold years 1000 to 9999; "5/3/26" read as year 26 is a typo, not a date.
     */
    private static function inRange(CarbonImmutable $date): ?CarbonImmutable
    {
        return $date->year >= 1000 && $date->year <= 9999 ? $date : null;
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
        if (is_int($value)) {
            return $value;
        }

        $number = $this->toNumber($value);

        // Stay inside BIGINT and PHP's int; anything larger is dropped rather than failing the insert.
        return null === $number || abs((float) $number) >= self::INTEGER_LIMIT ? null : (int) round((float) $number);
    }

    private function toDecimal(mixed $value): ?string
    {
        $number = $this->toNumber($value);

        // DECIMAL(18,4) holds 14 digits before the point; MySQL rejects anything larger outright.
        return null === $number || abs((float) $number) >= self::DECIMAL_LIMIT ? null : $number;
    }

    /**
     * Reads a cell as a plain number string. Text has to be a single number, optionally wrapped in a
     * currency symbol or code, a sign or accounting parentheses; anything else is not guessed at.
     */
    private function toNumber(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Avoids "1.0E+15" and float noise such as 0.30000000000000004.
            return is_finite($value) ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') : null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = str_replace(["\u{2212}", "\u{00A0}", "\u{202F}"], ['-', ' ', ' '], trim((string) $value));
        $negative = false;

        if (1 === preg_match('/^\((.*)\)$/u', $text, $match)) {
            [$negative, $text] = [true, trim($match[1])];
        }

        if (str_starts_with($text, '-')) {
            [$negative, $text] = [ ! $negative, ltrim(mb_substr($text, 1))];
        }

        $text = preg_replace(['/^(?:\p{Sc}|[A-Za-z]{1,3}\.?(?![A-Za-z]))\s*/u', '/\s*(?:\p{Sc}|(?<![A-Za-z])[A-Za-z]{1,3}\.?)$/u'], '', $text);

        if (null !== $text && str_starts_with($text, '-') && ! $negative) {
            [$negative, $text] = [true, mb_substr($text, 1)];
        }

        // Spaces and apostrophes count only as thousands separators: "1 234,50", "1'234.50".
        $text = preg_replace("/(?<=\d)[ '\u{2019}](?=\d{3}(?!\d))/u", '', (string) $text);

        if (null === $text || 1 !== preg_match('/^[\d.,]*\d[\d.,]*$/', $text)) {
            return null;
        }

        $number = $this->resolveSeparators($text);

        return null === $number ? null : ($negative ? '-' : '') . $number;
    }

    private function resolveSeparators(string $text): ?string
    {
        $lastDot = mb_strrpos($text, '.');
        $lastComma = mb_strrpos($text, ',');

        if (false !== $lastDot && false !== $lastComma) {
            // Whichever separator comes last is the decimal one: 1.234,56 and 1,234.56.
            $decimal = $lastDot > $lastComma ? '.' : ',';
            [$whole, $fraction] = explode($decimal, $text, 2);

            return self::isGrouped($whole, '.' === $decimal ? ',' : '.') && ctype_digit($fraction)
                ? str_replace(['.', ','], '', $whole) . '.' . $fraction
                : null;
        }

        foreach ([',', '.'] as $separator) {
            if (0 === mb_substr_count($text, $separator)) {
                continue;
            }

            // 1,234 and 1.234.567 are grouped thousands. A single comma otherwise is a decimal comma (12,5)
            // and a single dot a decimal point; 0,500 is a decimal because a group never starts with 0.
            if (self::isGrouped($text, $separator) && (',' === $separator || mb_substr_count($text, '.') > 1)) {
                return str_replace($separator, '', $text);
            }

            return 1 === mb_substr_count($text, $separator) ? str_replace(',', '.', $text) : null;
        }

        return $text;
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
                return self::inRange(CarbonImmutable::instance($date));
            }
        }

        // No free-form fallback: Carbon::parse reads "x", "Mon" or "March" as dates relative to today.
        return null;
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
