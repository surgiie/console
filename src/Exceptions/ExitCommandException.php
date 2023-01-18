<?php

namespace Surgiie\Console\Exceptions;

use Exception;

class ExitCommandException extends Exception
{
    public function __construct(string $message, int $status = 1, string $level = 'error')
    {
        $this->message = $message;
        $this->status = $status;
        $this->level = $level;
    }

    /**Get the level to use for the exit exception message.*/
    public function getLevel()
    {
        return $this->level;
    }

    /**Get the status code.*/
    public function getStatus(): int
    {
        return $this->status;
    }
}
