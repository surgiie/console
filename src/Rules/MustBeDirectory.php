<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MustBeDirectory implements ValidationRule
{
    protected string $error = 'The :name :type directory does not exist.';

    public function __construct(string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_dir($value)) {
            $fail($this->error);
        }
    }
}
