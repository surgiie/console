<?php

namespace Surgiie\Console\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

class FileMustNotExist implements InvokableRule
{
    /**The error message. */
    protected string $error = 'The :name :type file already exists.';

    /**Construct new Rule instance.*/
    public function __construct(?string $error = null)
    {
        if (! is_null($error)) {
            $this->error = $error;
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if (is_file($value)) {
            $fail($this->error);
        }
    }
}
