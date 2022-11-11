# Console

![Tests](https://github.com/surgiie/console/actions/workflows/tests.yml/badge.svg)

A base command and set of useful support trait/classes for [Laravel](https://laravel.com) or [Laravel Zero](https://laravel-zero.com) commands.

## Installation

```bash
composer require surgiie/console
```

## Features


### Merged Data

All arguments and options are merged into a single `$this->data` collection, giving a fluent object to pull and work with option/arg data.

```php
<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;
use Surgiie\Console\Concerns\WithValidation;

class ExampleCommand extends Command
{
    public function handle()
    {
        $this->data->get("some-arg-or-option", 'default');
    }
}

```

### Store Values for performance

Helpful for caching instances into a property if going to be called repeatedly.
```php

protected function example()
{
    // get a property or store it if it doesnt exist
    return $this->getProperty('example', fn () => new Example);
}
```
### Validation

Utilize Laravel Validation for Arguments & Options

```php
<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;
use Surgiie\Console\Concerns\WithValidation;

class ExampleCommand extends Command
{
    use WithValidation;

    protected $signature = "example {--iterations=}";

    public function rules()
    {
        return [
            'interations'=>'required|int'
        ];
    }

    public function messages()
    {
        // custom validation messages
        return ['...'];
    }

    public function attributes()
    {
        // custom validation attributes
        return ['...'];
    }
}
```


### Get Or Ask For Input


### Arbitrary Options
To allow your command to accept arbitrary options not part of the command signature:

```php

<?php
namespace App\Console\Commands;

use Surgiie\Console\Command;

class ExampleCommand extends Command
{

    public function arbitraryOptions()
    {
        return true;
    }

    public function handle()
    {
        // available if --something option is passed:
        $something = $this->arbitraryData->get("something")
    }
}

```
### Show Peformance & Memory Usage

At the end of your command, you will have a line message showing more info about how your command performed:

`PEFORMANCE  Memory: 9.60MB|Execution Time: 1.56ms`

By setting this option within your command:

```php

public function showPerformanceStats()
{
    return true;
}

```



### Argument & Option Transformation/Formatting

Transform, format, or sanitize input and arguments easily before `handle` is called, using a validation rule like syntax:

```php

protected function transformers()
{
    return [
        'some-option'=>['trim', 'ucwords']
    ];
}

protected function transformersAfterValidation()
{
    return [
        'some-option'=>['strtoupper']
    ];
}


```
**Note* - For more, read the [surgiie/tranformer](https://github.com/surgiie/tranformer) readme docs.

**Note** - The base command performs some default tranformations before custom defined ones, they are as follows:

* All options with "date" in their name, are automatically converted to `\Carbon\Carbon` instances.

### Check Requirements
Provide a list of requirements before the handle is called:

```php

    public function requireSomethingOrFail()
    {
        // throw an exception:
        throw new FailedRequirementException("Failed to meet some requirment");
        // or return an error string:
        return "Failed to meet some requirement";
    }

    public function requirements()
    {
        return [
            'docker', //default for a string value checks if 'docker' is in $PATH with `which docker`
            "requireSomethingOrFail", //unless the method exists on the class, it will call that instead
            function () { // can use callback that returns an error string
                $process = new Process(['docker', 'info']);

                $process->run();

                return $process->getOutput() == '' ? 'Docker is not running' : '';
            },
            // can use also class constants or instances that have __invoke method.
            new Example,
            Example::class
        ];
    }
```
**Note** If any of the methods above dont resolve to a `$PATH` available dependency or return a string/raise exception, the handle method will not be called and the returned error will be displayed.

### Asking For Input Helper

```php

<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;
use Surgiie\Console\Concerns\WithValidation;

class ExampleCommand extends Command
{
    use WithValidation;

    protected $signature = 'example {--example=}{--key=}';

    public function rules()
    {
        // nullable/optional but if given validate options
        return ['example'=>['nullable', 'max:30'], 'key'=>'nullable|size:32'];
    }

    public function handle()
    {
        // get the example option value or ask for it if not already present.
        // by setting the option rules as nullable, you can optionally accept the option
        // but will ask for it if not given.
        $example = $this->getOrAskForInput('example');
        // you can also use validation if the WithValidation trait is available.
        // this will exit with an error if rules fail.
        $example = $this->getOrAskForInput('example', rules: ['required', 'max:30']);

        // you can ask for secret input as well
        $key = $this->getOrAskForInput('key', secret: true);

        // you can also keep asking for the user to confirm input until original input and confirmation match.
        $key = $this->getOrAskForInput('key', secret: true, confirm: true);
    }
}

```

### Render Files With Blade Engine:
An exented version of the blade engine is available to compile any textual file:


```php

public function handle()
{
    $contents = $this->compile('/some/file', ['var'=>'example']);
}

```



