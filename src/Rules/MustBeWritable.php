<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MustBeWritable implements ValidationRule
{
    protected string $error = 'The :name :type is not writable.';

    public function __construct(string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_writable($value)) {
            $fail($this->error);
        }
    }
}
