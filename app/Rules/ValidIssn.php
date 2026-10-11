<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIssn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\d{4}-\d{3}[\dX]$/', $value)) {
            $fail('ISSN harus berformat 1234-567X.');

            return;
        }
        $s = str_replace('-', '', $value);
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $s[$i] * (8 - $i);
        }
        $sum += ($s[7] === 'X' ? 10 : (int) $s[7]);
        if ($sum % 11 !== 0) {
            $fail('Checksum ISSN tidak valid.');
        }
    }
}
