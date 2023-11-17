<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FileMustNotExist implements ValidationRule
{
    /**
     * The default error for validation failures.
     *
     * @param string
     */
    protected string $error = 'The :name :type already exists.';

    /**
     * Construct a new FileMustNotExist instance.
     */
    public function __construct(string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    /**
     * Validate the given value and fail with an error if it is invalid.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_file($value)) {
            $fail($this->error);
        }
    }
}
