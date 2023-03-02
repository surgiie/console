<?php

namespace Surgiie\Console\Exceptions;

use Exception;

class ExitCommandException extends Exception
{
    /**
     * Construct a new ExitCommandException instance.
     */
    public function __construct(string $message, int $status = 1, string $level = 'error')
    {
        $this->message = $message;
        $this->status = $status;
        $this->level = $level;
    }

    /**
     * Get the message level to use for the exit exception message.
     *
     * @return void
     */
    public function getLevel()
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
