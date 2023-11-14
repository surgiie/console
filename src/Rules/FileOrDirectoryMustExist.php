<?php

namespace Surgiie\Console\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FileOrDirectoryMustExist implements ValidationRule
{
    protected string $error = 'The :name :type file or directory does not exist.';

    public function __construct(string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! file_exists($value)) {
            $fail($this->error);
        }
    }
}
