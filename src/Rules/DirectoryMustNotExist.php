<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DirectoryMustNotExist implements ValidationRule
{
    /**
     * The default error for validation failures.
     *
     * @param string
     */
    protected string $error = 'The :name :type directory already exists.';

    /**
     * Construct a new DirectoryMustNotExist instance.
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
        if (is_dir($value)) {
            $fail($this->error);
        }
    }
}
