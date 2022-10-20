<?php

namespace Surgiie\Console\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;

trait WithValidation
{
    use FromPropertyOrMethod;

    /**Get path to the lang directory for validation message language.*/
    protected function getValidationLangPath(): string
    {
        return realpath(__DIR__.'/../resources/lang');
    }

    /**
     * Make a new validator using the given data.
     */
    protected function validator(array $data, ?array $rules = null, ?array $messages = null, ?array $attributes = null)
    {
        $app = app();

        $loader = new FileLoader(new Filesystem, $this->getValidationLangPath());

        $translator = new Translator($loader, $app->currentLocale() ?? 'en');

        $factory = new ValidatorFactory($translator, $app);

        return $factory->make(
            $data,
            $rules ?: $this->fromPropertyOrMethod('rules', []),
            $messages ?: $this->fromPropertyOrMethod('messages', []),
            $attributes ?: $this->fromPropertyOrMethod('attributes', []),
        );
    }
}
