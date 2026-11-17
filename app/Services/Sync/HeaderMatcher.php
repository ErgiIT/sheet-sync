<?php

declare(strict_types=1);

namespace App\Services\Sync;

class HeaderMatcher
{
    /**
     * "Product Name", "product_name" and " PRODUCT-NAME " all compare equal.
     */
    public static function normalize(string $header): string
    {
        return trim((string) preg_replace('/[\s_\-]+/u', '_', mb_strtolower(trim($header))), '_');
    }

    /**
     * Finds where each configured source column sits in this sheet's header row, so files with
     * extra or reordered columns map the same way. Unmatched source columns are left out.
     *
     * @param  list<mixed>  $headerRow
     * @param  list<string>  $sourceColumns
     * @return array<string, int> source column => zero-based cell index
     */
    public function match(array $headerRow, array $sourceColumns): array
    {
        $positions = [];

        foreach ($headerRow as $index => $header) {
            $key = self::normalize((string) $header);

            if ('' !== $key && ! isset($positions[$key])) {
                $positions[$key] = $index;
            }
        }

        $matched = [];

        foreach ($sourceColumns as $sourceColumn) {
            $key = self::normalize($sourceColumn);

            if (isset($positions[$key])) {
                $matched[$sourceColumn] = $positions[$key];
            }
        }

        return $matched;
    }
}
