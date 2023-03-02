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

### Check if options were passed:
```php

<?php

namespace App\Console\Commands;

use Surgiie\Console\Command;
use Surgiie\Console\Concerns\WithValidation;

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
use Surgiie\Console\Concerns\WithValidation;

class ExampleCommand extends Command
{
    use WithValidation;

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

### Get Or Ask For Input

Get the value of an input or option or ask the user to input it if empty:

```php

<?php
namespace App\Console\Commands;

use Surgiie\Console\Command;

class ExampleCommand extends Command
{

    protected $signature = "example {--name=}";

    public function handle()
    {
        // user will be asked if --name was not passed/set:
        $something = $this->getOrAskForInput("name")

        $something = $this->getOrAskForInput("name", [
            // can use with validation:
            'rules'=> ['required', 'max:20'],
            // can use transformers:
            'transformers'=> ['trim', 'strtoupper'],
            'transformersAfterValidation'=> ['trim', 'strtoupper'],
            // have user confirm by asking for value twice until values match:
            'confirm'=>true,
            // hide input
            'secret'=>true,
        ]);

}

```

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


### Render Files With Blade Engine:
An exented version of the blade engine is available to compile any textual file:


```php

public function handle()
{
    $contents = $this->compile('/some/file', ['var'=>'example']);
}

// set a custom path for compiled/cached files. Default is /tmp/.compiled
public function bladeCompiledPath(): string|null
{
    return '/custom/directory';
}

```



### Run Tasks Concurrently With A Loader
To give users of your a better visual experience for tasks, you may desire to show a nice spinner animation, Note that in order to achieve a spinner animation while running the task, 2 child PHP processes are used via [spatie/fork](https://github.com/spatie/fork), one process is for the spinner animation and one for the task function you pass in. This relies on escape sequences and the php `pcntl` extenion. This feature is only supported on unix based os's and on windows, this will not run the task concucrrently.

```php
$task = $this->runTask("Doing stuff...", function($task){
    sleep(4); // simulating stuff.

    return true; // return whether task succeeded or not.
});

if($task->succesful()){
    // do stuff.
}
```

There is 1 annoying caveat about this and that is since the task is executed in a child process, it won't be able to directly change any variables from the parent scope even if with `use` keyword to inherit parent scope. Meaning, something like this wont work:

```php
$data = [];
$this->runTask("Doing stuff...", function($task) use (&$data){
    $data['new_value'] = 'foobar';
    return true;
});
dd($data); // still empty [] array. :/
```

To get around this limitation, you may use the `remember` method on the task object passed into the callback to persist any serializable data:

```php
$data = [];
$task = $this->runTask("Doing stuff....", function($task){
    $task->remember(['foo'=>'bar']);
    return true;
});
dd($task->data()); // ['foo'=>'bar']
```

This works by using `serialize` on the data and writing it to a temporary file within your application's storage directory then calls `unserialize` on the data back in the parent process.


**Note** If prefer not to run tasks concurrently or with a spinner as mentioned above, this functionality can be disabled with `Surgiie\Console\Command::disableConcurrentTasks()`. This is desired in tests as it prevents overhead when testing commands.