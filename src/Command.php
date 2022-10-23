<?php

namespace Surgiie\Console;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Contracts\NewLineAware;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command as LaravelZeroCommand;
use Surgiie\Blade\Blade;
use Surgiie\Console\Concerns\FromPropertyOrMethod;
use Surgiie\Console\Concerns\WithTransformers;
use Surgiie\Console\Concerns\WithValidation;
use Surgiie\Console\Exceptions\FailedRequirementException;
use Surgiie\Transformer\DataTransformer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function Termwind\render;
use function Termwind\renderUsing;

if (class_exists(LaravelZeroCommand::class)) {
    abstract class BaseCommand extends LaravelZeroCommand
    {
    }
} else {
    abstract class BaseCommand extends LaravelCommand
    {
    }
}

abstract class Command extends BaseCommand
{
    use FromPropertyOrMethod;

    /**The merged arguments and options.*/
    protected Collection $data;

    /**The data that was arbitrary.*/
    protected Collection $arbitraryData;

    /**Construct a new Command instance.*/
    public function __construct()
    {
        parent::__construct();

        if ($this->fromPropertyOrMethod('arbitraryOptions', false)) {
            $this->arbitraryData = collect();

            $this->ignoreValidationErrors();
        }
    }

    public function __set(string $name, mixed $value)
    {
        // prevent depecration notice in 8.2 for dynamic properties.
    }

    /**Get the console components instance.*/
    public function getComponents()
    {
        return $this->components;
    }

    /**Check if a class uses a given trait.*/
    protected function classUsesTrait($class, string $trait): bool
    {
        return once(fn () => array_key_exists($trait, class_uses($class)));
    }

    /**
     * Exit script with code and display optional error.
     */
    protected function exit(string $error = '', int $code = 1, string $level = 'error'): void
    {
        if ($error) {
            $this->components->{$level}($error);
        }
        exit($code);
    }

    /**
     * Renders the given view.
     */
    protected function consoleView(string $view, array $data, int $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        renderUsing($this->output);

        $view = rtrim($view, '.php');
        $path = base_path("resources/views/console/$view.php");

        if (! is_file($path)) {
            $path = realpath(__DIR__."/resources/views/$view.php");
        }

        render((string) $this->compile($path, $data), $verbosity);
    }

    /**Get the output object.*/
    public function getConsoleOutput()
    {
        return $this->output;
    }

    /**Run a new command task.*/
    public function runTask(string $title = '', $task = null)
    {
        if (! extension_loaded('pcntl')) {
            if (! method_exists($this, 'task')) {
                throw new BadMethodCallException('runTask relies on nunomaduro/laravel-console-task when running on windows.');
            }

            return $this->task($title, $task);
        }
        $task = (new CommandTask($title, $this, $task));

        $task->run();

        return $task;
    }

    /**Get a property or create it using the given callback. */
    protected function getProperty($property, Closure $createWith)
    {
        if (! property_exists($this, $property)) {
            return $this->$property = $createWith();
        }

        return $this->$property;
    }

    /**Return a new blade instance.*/
    protected function blade(): Blade
    {
        return $this->getProperty('blade', fn () => new Blade(
            container: $this->laravel,
            filesystem: new Filesystem,
        ));
    }

    /**Compile a file using blade engine.*/
    public function compile(string $path, array $data = []): string
    {
        return $this->blade()->compile($path, $data);
    }

    /**$this->components->line(),but with the ability to edit color/title.*/
    protected function message(string $title, string $content, string $bg = 'gray', string $fg = 'white')
    {
        $this->consoleView('line', [
            'bgColor' => $bg,
            'marginTop' => ($this->output instanceof NewLineAware && $this->output->newLineWritten()) ? 0 : 1,
            'fgColor' => $fg,
            'title' => $title,
            'content' => $content,
        ]);
    }

    /**Print a debug message.*/
    protected function debug(string $message)
    {
        $this->message('DEBUG', $message, 'gray', 'white');
    }

    /**
     * Initialize command.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($this->fromPropertyOrMethod('arbitraryOptions', false)) {
            global $argv;
            $parser = new OptionsParser($argv);
            $definition = $this->getDefinition();

            foreach ($parser->parse() as $name => $data) {
                if (! $definition->hasOption($name)) {
                    $this->arbitraryData->put($name, $data['value']);
                    $this->addOption($name, mode: $data['mode']);
                }
            }
            //rebind input definition
            $input->bind($definition);
        }
    }

    /**
     * Ask for input and optionally validate if trait is used.
     */
    protected function getOrAskForInput(string $name, bool $confirm = false, bool $secret = false, array $rules = [], array $messages = [], array $attributes = [])
    {
        $input = $this->data->get($name);

        if (! empty($input)) {
            return $input;
        }

        $validate = function ($input) use ($name, $rules, $messages, $attributes) {
            if ($this->classUsesTrait($this, WithValidation::class)) {
                $validator = $this->validator([$name => $input], [$name => $rules], $messages, $attributes);

                if ($validator->fails()) {
                    $this->displayValidationErrors($validator, isUserInput: true);
                }
            }
        };
        $method = $secret ? 'secret' : 'ask';
        $label = str_replace(['_', '-'], [' ', ' '], $name);

        $message = "Enter $label:";

        $this->message('INPUT', $message, fg: 'white', bg: 'green');
        $input = $this->$method('ctrl-c to exit');

        $validate($input);

        if ($confirm) {
            $this->message('CONFIRM INPUT', "Confirm $label", fg: 'black', bg: 'cyan');
            $confirmInput = $this->$method('ctrl-c to exit');

            while ($input != $confirmInput) {
                $this->message('CONFIRM FAILED', "Try $label confirmation again", fg: 'white', bg: 'red');
                $confirmInput = $this->$method('ctrl-c to exit');
            }
        }

        return $input;
    }

    /**
     * Check a defined depency.
     *
     * @throws \Surgiie\Console\Exceptions\FailedRequirementException
     */
    protected function checkRequirement($requirement)
    {
        $isString = is_string($requirement);
        if (is_callable($requirement)) {
            $error = $requirement();
        } elseif ($isString && method_exists($this, $requirement)) {
            $error = App::call([$this, $requirement]);
        } elseif ($isString && class_exists($requirement)) {
            $instance = app($requirement);
            $error = App::call($instance);
        } elseif ($isString) {
            $process = (new Process(['which', $requirement]));

            $process->run();

            $error = $process->getOutput() == '' ? "This command requires $requirement." : '';
        } else {
            throw new InvalidArgumentException('Couldnt check requirement');
        }

        if ($error = trim($error ?? '')) {
            throw new FailedRequirementException($error);
        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $status = 0;

        // check requirements as defined by developer
        foreach ($this->fromPropertyOrMethod('requirements', []) as $requirement) {
            try {
                $this->checkRequirement($requirement);
            } catch (FailedRequirementException $e) {
                $this->exit($e->getMessage());
            }
        }

        // merge the data together.
        $data = (new DataTransformer(array_merge($this->arguments(), $this->options()), ['*date*' => ['?', Carbon::class]]))->transform();

        $this->data = collect($data)->filter(function ($v) {
            return ! is_null($v);
        });

        // transform the data before validation
        if ($withTransformers = $this->classUsesTrait($this, WithTransformers::class) && $transformers = $this->transformers()) {
            $this->data = collect(
                $this->transformData($this->data->all(), $transformers)
            );
        }

        // validate
        $this->validate();

        // post validation transformation
        if ($withTransformers && $transformers = $this->transformersAfterValidation()) {
            $this->data = collect(
                $this->transformData($this->data->all(), $transformers)
            );
        }

        // separate the arbitraryData from main.
        if ($this->fromPropertyOrMethod('arbitraryOptions', false)) {
            $this->data = $this->data->filter(function ($value, $name) {
                $isArbitrary = $this->arbitraryData->has($name);

                // update the value in case it has gone through transformation
                if ($isArbitrary) {
                    $this->arbitraryData->put($name, $value);
                }

                return ! $isArbitrary;
            });
        }

        // run the command
        $ms = Benchmark::measure(function () use (&$status) {
            $status = $this->executeCommand();
        });

        // lastly show how we did if specified.
        if ($this->fromPropertyOrMethod('showPerformanceStats', false) !== false) {
            $this->newLine();
            $this->message(
                'Peformance',
                'Memory: '.$this->getMemoryUsage().'|Execution Time: '.number_format($ms, 2, thousands_separator: '').'ms',
                bg: 'cyan'
            );
        }

        return $status;
    }

    /**
     * Get memory usage bytes into a more human friendly label.
     */
    protected function getMemoryUsage()
    {
        $bytes = memory_get_usage();
        $labels = [
            'B',
            'kB',
            'MB',
            'GB',
            'TB',
        ];
        $length = count($labels);
        for ($i = 0, $length; $i < $length && $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return number_format($bytes, 2).$labels[$i];
    }

    /**Validate the current data for options and arguments.*/
    protected function validate(): void
    {
        if ($this->classUsesTrait($this, WithValidation::class)) {
            $validator = $this->validator($this->data->all());

            if ($validator->fails()) {
                $this->displayValidationErrors($validator);
            }
        }
    }

    /**Display validation errors.*/
    protected function displayValidationErrors(Validator $validator, bool $isUserInput = false)
    {
        foreach ($validator->messages()->getMessages() as $name => $errors) {
            $isOption = $this->hasOption($name);

            if ($isUserInput) {
                $name = str_replace(['-', '_'], [' ', ' '], $name);
                $type = '';
            } else {
                $name = $isOption ? '--'.$name : $name;
                $type = $isOption ? 'option' : 'argument';
            }

            $this->components->error(str_replace([':name', ':type'], [$name, $type], $errors[0]));
        }

        $this->exit();
    }

    /**
     * Execute the command's handle method.
     */
    private function executeCommand()
    {
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        return (int) $this->laravel->call([$this, $method]);
    }
}
