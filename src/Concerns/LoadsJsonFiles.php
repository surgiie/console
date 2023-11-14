<?php

namespace Surgiie\Console\Concerns;

use InvalidArgumentException;
use JsonException;

trait LoadsJsonFiles
{
    protected function formatJsonParseError(string $error): string
    {
        switch ($error) {
            case JSON_ERROR_DEPTH:
                return ' - Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return ' - Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return ' - Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return ' - Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return ' - Unknown error';
        }
    }

    public function loadJsonFile(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("The json file '$path' does not exist.");
        }

        $data = json_decode(file_get_contents($path), JSON_OBJECT_AS_ARRAY);

        $error = json_last_error();

        if ($error !== JSON_ERROR_NONE) {
            throw new JsonException($this->formatJsonParseError(json_last_error()));
        }

        return $data;
    }
}
