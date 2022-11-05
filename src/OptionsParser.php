<?php

namespace Surgiie\Console;

use Illuminate\Support\Arr;
use Surgiie\Console\Exceptions\DuplicateException;
use Symfony\Component\Console\Input\InputOption;

class OptionsParser
{
    /**
     * The options to parse.
     */
    protected array $options = [];

    /**
     * Construct new OptionParser instance.
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    /**
     * Set the options to parse.
     */
    public function setOptions(array $options): static
    {
        $this->options = array_filter($options);

        return $this;
    }

    /**
     * Parse a token for an --option or --option=value format.
     */
    protected function parseOption(string $token): array
    {
        // match for a --option or --option=value string.
        preg_match('/--([^=]+)(=)?(.*)/', $token, $match);

        return $match;
    }

    /**
     * Parse the set options for key/values and opitons mode.
     */
    public function parse(): array
    {
        $options = [];
        foreach ($this->options as $token) {
            $match = $this->parseOption($token);

            if (! $match) {
                continue;
            }

            $name = $match[1];
            $equals = $match[2] ?? false;
            $value = $match[3] ?? false;

            $optionExists = array_key_exists($name, $options);

            if ($optionExists && ($value || $equals)) {
                $options[$name] = [
                    'mode' => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    'value' => $options[$name]['value'] ?? [],
                ];
                $options[$name]['value'] = Arr::wrap($options[$name]['value']);
                $options[$name]['value'][] = $value;
            } elseif ($value) {
                $options[$name] = [
                    'mode' => InputOption::VALUE_REQUIRED,
                    'value' => $value,
                ];
            } elseif (! $optionExists) {
                $options[$name] = [
                    'mode' => ($value == '' && $equals) ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_NONE,
                    'value' => ($value == '' && $equals) ? '' : true,
                ];
            } else {
                throw new DuplicateException("The '$name' option has already been provided.");
            }
        }

        return $options;
    }
}
