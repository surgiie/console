<?php

namespace Surgiie\Console\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

class MustBeExecutable implements InvokableRule
{
    /**
     * The error message when validation fails.
     *
     * @var string
     */
    protected string $error = 'The :name :type is not executable.';

    /**
     * Construct new MustBeExecutable instance.
     *
     * @param  string|null  $error
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
        if (! is_executable($value)) {
            $fail($this->error);
        }
    }
}
