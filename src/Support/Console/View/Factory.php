<?php

namespace Surgiie\Console\Support\Console\View;

use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use InvalidArgumentException;

class Factory extends ComponentsFactory
{
    public function __call($method, $parameters)
    {
        try {
            return parent::__call($method, $parameters);
        } catch (InvalidArgumentException) {
            $component = '\Surgiie\Console\Support\Console\View\Components\\'.ucfirst($method);

            throw_unless(class_exists($component), new InvalidArgumentException(sprintf(
                'Console component [%s] not found.', $method
            )));

            return with(new $component($this->output))->render(...$parameters);
        }
    }
}
