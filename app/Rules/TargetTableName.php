<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SqlIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TargetTableName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ( ! is_string($value) || ! SqlIdentifier::isValid($value)) {
            $fail('The :attribute must start with a lowercase letter and contain only a-z, 0-9 and underscores (max 64).');

            return;
        }

        if (SqlIdentifier::isProtectedTable($value)) {
            $fail("The table \"{$value}\" is used by the application itself and cannot be a sync target.");
        }
    }
}
