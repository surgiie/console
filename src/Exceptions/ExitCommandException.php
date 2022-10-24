<?php

namespace Surgiie\Console\Exceptions;

use Exception;

class ExitCommandException extends Exception
{
    public function __construct(string $message, int $status = 1)
    {
        $this->message = $message;
        $this->status = $status;
    }

    /**Get the status code.*/
    public function getStatus(): int
    {
        return $this->status;
    }
}
