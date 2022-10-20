<?php

namespace Surgiie\Console\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

class IsWriteable implements InvokableRule
{
    /**The error message. */
    protected string $error = 'The :name :type is not writable.';

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
        if (! is_writable($value)) {
            $fail($this->error);
        }
    }
}
