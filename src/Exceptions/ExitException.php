<?php

namespace Surgiie\Console\Exceptions;

use Exception;

class ExitException extends Exception
{
    public function __construct(string $message, int $status = 1, string $level = 'error')
    {
        $this->message = $message;
        $this->status = $status;
        $this->level = $level;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
