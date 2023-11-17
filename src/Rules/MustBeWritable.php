<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MustBeWritable implements ValidationRule
{
    /**
     * The default error for validation failures.
     *
     * @param string
     */
    protected string $error = 'The :name :type is not writable.';

    /**
     * Construct a new MustBeWritable instance.
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
        if (! is_writable($value)) {
            $fail($this->error);
        }
    }
}
