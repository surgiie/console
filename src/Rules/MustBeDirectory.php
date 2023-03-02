<?php

namespace Surgiie\Console\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

class MustBeDirectory implements InvokableRule
{
    /**
     * The error message when validation fails.
     */
    protected string $error = 'The :name :type directory does not exist.';

    /**
     * Construct new MustBeDirectory instance.
     */
    public function __construct(?string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    /**
     * Check if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if (! is_dir($value)) {
            $fail($this->error);
        }
    }
}
