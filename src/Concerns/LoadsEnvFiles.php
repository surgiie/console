<?php

namespace Surgiie\Console\Concerns;

use Dotenv\Dotenv;
use InvalidArgumentException;

trait LoadsEnvFiles
{
    /**
     * Loads a env file variables into $_ENV and return the result.
     */
    public function loadEnvFileVariables(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("The env file '$path' file does not exist.");
        }

        $env = basename($path);

        $dotenv = Dotenv::createImmutable(dirname($path), $env);

        return $dotenv->load();
    }
}
