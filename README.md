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

class ExampleCommand extends Command
{
    public function handle()
    {
        $this->data->get("some-arg-or-option", 'default');
    }
}

```

### Check if options were passed:
```php

<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;

class ExampleCommand extends Command
{
    protected $signature = "example {--iterations=}";

    public function handle()
    {
        // check if the user passed the --iterations flag in the command call.
        if($this->optionWasPassed("iterations")){

        }
    }
}


```


### Store values for performance into cache array

Helpful for caching instances into a array property if going to be called repeatedly.

```php

protected function example()
{
    // get a value or store it in the cache array if it doesnt exist
    return $this->fromArrayCache('example', fn () => new Example);
}
```
### Validation

Utilize Laravel Validation for Arguments & Options

```php
<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;

class ExampleCommand extends Command
{

    protected $signature = "example {--iterations=}";

    public function rules()
    {
        return [
            'interations'=>'required|numeric'
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


### Argument & Option Transformation/Formatting

Transform, format, or sanitize input and arguments easily before `handle` is called, using a validation rule like syntax:

```php

protected function transformers() : array
{
    return [
        'some-option'=>['trim', 'ucwords']
    ];
}

protected function transformersAfterValidation() : array
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

    public function requireSomethingOrFail(): string
    {
        // throw an exception:
        throw new FailedRequirementException("Failed to meet some requirment");
        // or return an error string:
        return "Failed to meet some requirement";
    }

    public function requirements(): array
    {
        return [
            'docker', //default for a string value checks if 'docker' is in $PATH with `which <value>`
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
**Note** If any of the methods above return an error string or raise `FailedRequirementException`, the `handle` method will not be called.

In addition, if you  need custom logic to check if a string path is available, you can overwrite the following method:

```php
/**
 * Check if a executable is in $PATH.
 *
 * @param string $requirement
 * @return string
 */
protected function checkWhichPath(string $requirement): string
{
    $process = (new Process(['which', $requirement]));

    $process->run();

    return $process->getOutput() == '' ? "This command requires $requirement." : '';
}
```

### Render Files With Blade Engine:
An exented version of the blade engine is available to compile any textual file:


```php

public function handle()
{
    $contents = $this->compile('/some/file', ['var'=>'example']);
}

// set a custom path for compiled/cached files. Default is /tmp/.compiled or tests/.compiled when running unit tests
public function bladeCompiledPath(): string
{
    return '/custom/directory';
}

```



### Long Running Tasks

To give a better visual experience for long running tasks, you can use the `runTask` method:

```php
$this->runTask("Doing stuff", function($task){
    sleep(4); // simulating stuff.

    return true; // return whether task succeeded or not.

}, spinner: true); // show spinner while task is running.
```

**Note** - In order to show a animated spinner, the pcntl PHP extension must be installed. When this extension is not available, a static version of the spinner will appear instead.

#### Custom Task Finished Text

When the task is completed, you can customize text shown the task has finished:
```php
$this->runTask("Doing stuff", function($task){
    sleep(4); // simulating stuff.
}, finishedText: "Finished doing stuff");
```


## Call Succeeded/Failed Functions Automatically

Automatically calls `succeeded` and `failed` based on `handle` exit code.

```php

public function succeeded()
{
    // called when handle int is 0
    $this->components->info("It ran successfully");
}
public function failed()
{
    // called when handle int is 1
    $this->components->info("It didnt run successfully");
}

```