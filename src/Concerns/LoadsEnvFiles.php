<?php

namespace Surgiie\Console\Concerns;

use Dotenv\Dotenv;
use InvalidArgumentException;

trait LoadsEnvFiles
{
    /**
     * Parse a dot env file into an array of variables.
     */
    public function getEnvFileVariables(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("The env file '$path' does not exist.");
        }

        return Dotenv::parse(file_get_contents($path));
    }

    /**
     * Parse .env file and load it into the environment.
     */
    public function loadEnvFileVariables(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("The env file '$path' does not exist.");
        }

        $env = basename($path);

        $dotenv = Dotenv::createImmutable(dirname($path), $env);

        return $dotenv->load();
    }
}
