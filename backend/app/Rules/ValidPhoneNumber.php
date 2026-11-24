<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    public function __construct(
        private readonly string $region,
        private readonly bool $mobileOnly = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PhoneNumber::normalize($value, $this->region, $this->mobileOnly) !== null) {
            return;
        }

        $fail($this->mobileOnly
            ? 'Saisissez un numéro de téléphone mobile valide.'
            : 'Saisissez un numéro de téléphone valide.');
    }
}
