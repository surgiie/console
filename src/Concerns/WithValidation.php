<?php

namespace Surgiie\Console\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;

trait WithValidation
{
    use FromPropertyOrMethod;

    /**
     * Get the path to directory holding the lang file for validation.
     */
    protected function getValidationLangPath(): string
    {
        return __DIR__.'/../resources/lang';
    }

    /**
     * Get the locale to use for validation.
     *
     * @return string.
     */
    protected function getValidationLangLocale(): string
    {
        return 'en';
    }

    /**
     * Create a new validator instance.
     *
     * @return \Illuminate\Validation\Validator
     */
    protected function validator(array $data, ?array $rules = null, ?array $messages = null, ?array $attributes = null)
    {
        $loader = new FileLoader(new Filesystem, $this->getValidationLangPath());

        $translator = new Translator($loader, $this->getValidationLangLocale());

        $factory = new ValidatorFactory($translator, $this->laravel);

        return $factory->make(
            $data,
            $rules ?: $this->fromPropertyOrMethod('rules', []),
            $messages ?: $this->fromPropertyOrMethod('messages', []),
            $attributes ?: $this->fromPropertyOrMethod('attributes', []),
        );
    }
}
