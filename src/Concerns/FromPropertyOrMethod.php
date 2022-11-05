<?php

namespace Surgiie\Console\Concerns;

use InvalidArgumentException;

trait FromPropertyOrMethod
{
    /**Get the value of a property or method where property is prioritized.*/
    protected function fromPropertyOrMethod(string $name, $default = null)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        if (method_exists($this, $name)) {
            return (function_exists('app') ? app() : $this->laravel)->call([$this, $name]);
        }

        if (! is_null($default)) {
            return $default;
        }

        throw new InvalidArgumentException("No such property or method called: $name");
    }
}
