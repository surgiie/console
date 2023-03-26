<?php

namespace Surgiie\Console\Exceptions;

use Exception;

class ExitException extends Exception
{
    /**
     * Construct a new ExitException instance.
     */
    public function __construct(string $message, int $status = 1, string $level = 'error')
    {
        $this->message = $message;
        $this->status = $status;
        $this->level = $level;
    }

    /**
     * Get the message level to use for the exit exception message.
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Get the status code to exit with.
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
