# Command

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
        return [...];
    }

    public function attributes()
    {
        // custom validation attributes
        return [...];
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

public function transformers()
{
    return [
        'some-option'=>['trim', 'ucwords']
    ];
}

public function transformersAfterValidation()
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



### Run Task With Spinner/Loader

**Note** - This requires terminal to support escape sequence and the PCNTL extenion. `composer install --ignore-platform-reqs` maybe used if needed, code will just fallback to a basic sync task:

```php

$task = $this->runTask("Doing stuff...", function($task){
    // command()->line() should be called for every new line so it doesnt interfere with the "loading/live text".
    $task->command()->line("Did something");

    sleep(4); // simulating stuff.

    $task->command()->line("Did something else");

    return true; // return whether task succeeded or not.

});

if($task->succesful()){
    // do stuff.
}

```

This will show a small animation spinner of: `⠏ -> ⠛ -> ⠹ -> ⢸ -> ⣰ -> ⣤ -> ⣆-> ⡇ Doing stuff...` and then every output you add with will be inserted above that.


Note that in order to achieve a spinner animation while running the task, 2 child PHP processes are used via [spatie/fork](https://github.com/spatie/fork), one process is for the spinner animation and one for the task function you pass in. Since the task is executed in a child process, it won't be able to directly change any variables from the parent scope even if you use `use` to inherit parent scope.


Meaning, something like this wont work:

```php
$data = [];
$this->runTask("Doing stuff...", function($task) use(&$data){
    $data['new_value'] = 'foobar';
    return true;
});

dd($data); // still empty [] array. :/

```

To get around this limitation, you may use the `persist` method on the task object passed into the callback to persist any serializable data:

```php
$data = [];
$task = $this->runTask("Doing stuff....", function($task){
    $task->persist(['foo'=>'bar']);
    return true;
});

dd($task->getData()); // ['foo'=>'bar']
```

This works by using `serialize` on the data you persist and writing it to a temporary file your application's storage directory then calls `unserialize` on the data back in the parent process.
