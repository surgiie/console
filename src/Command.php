<?php

namespace Surgiie\Console;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Contracts\NewLineAware;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command as LaravelZeroCommand;
use ReflectionException;
use Surgiie\Blade\Blade;
use Surgiie\Console\Concerns\FromPropertyOrMethod;
use Surgiie\Console\Concerns\WithTransformers;
use Surgiie\Console\Concerns\WithValidation;
use Surgiie\Console\Exceptions\ExitCommandException;
use Surgiie\Console\Exceptions\FailedRequirementException;
use Surgiie\Console\Support\Task;
use Surgiie\Transformer\Concerns\UsesTransformer;
use Surgiie\Transformer\DataTransformer;
use Symfony\Component\Console\Input\ArrayInput;
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
    use FromPropertyOrMethod, UsesTransformer;

    /**
     * The merged options and arguments.
     *
     * @var \Illuminate\Support\Collection
     */
    protected Collection $data;

    /**
     * Cached array for saving values repeatedly called.
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * The command tokens passed in the terminal as a string.
     *
     * @var string
     */
    protected string $commandTokensString = '';

    /**
     * The options that were arbitrary.
     *
     * @var \Illuminate\Support\Collection
     */
    protected Collection $arbitraryData;

    /**
     * Whether tasks are executed concurrently.
     *
     * @var bool
     */
    protected static $concurrentTasks = true;

    /**
     * Whether to cast date inputs to carbon instances.
     *
     * @var bool
     */
    protected bool $castDatesToCarbon = true;

    /**
     * Construct a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->arbitraryData = collect();

        if ($this->fromPropertyOrMethod('arbitraryOptions', false)) {
            $this->ignoreValidationErrors();
        }
    }

    /**
     * Set that tasks do not run concurrently.
     *
     * @return void
     */
    public static function disableConcurrentTasks()
    {
        static::$concurrentTasks = false;
    }

    /**
     * Set that tasks run concurrently.
     *
     * @return void
     */
    public static function enableConcurrentTasks()
    {
        static::$concurrentTasks = true;
    }

    /**
     * Get the console view components instance.
     *
     * @return Illuminate\Console\View\Components;
     */
    public function consoleViewComponents()
    {
        return $this->components;
    }

    /**
     * Check if a class uses a given trait.
     *
     * @param  mixed  $class
     * @param  string  $trait
     * @return bool
     */
    protected function classUsesTrait($class, string $trait): bool
    {
        return once(fn () => array_key_exists($trait, class_uses($class)));
    }

    /**
     * Throw an ExitCommandException.
     *
     * @param  string  $error
     * @param  int  $code
     * @param  string  $level
     * @return void
     *
     * @throws \Surgiie\Console\Exceptions\ExitCommandException
     */
    protected function exit(string $error = '', int $code = 1, string $level = 'error'): void
    {
        throw new ExitCommandException($error, $code, $level);
    }

    /**
     * Renders a console view with termwind renderer.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  int  $verbosity
     * @return void
     */
    protected function consoleView(string $view, array $data, int $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        renderUsing($this->output);

        $view = rtrim($view, '.php');
        $path = base_path("resources/views/console/$view.php");

        if (! is_file($path)) {
            $path = __DIR__."/resources/views/$view.php";
        }

        render((string) $this->compile($path, $data), $verbosity);
    }

    /**
     * Get the output style instance.
     *
     * @return \Illuminate\Console\OutputStyle
     */
    public function getOutputStyle()
    {
        return $this->output;
    }

    /**
     * Clear terminal line if the terminal supports escape sequences.
     *
     * @return void
     */
    public function clearTerminalLine()
    {
        if ($this->output->isDecorated()) {
            // Move the cursor to the beginning of the line
            $this->output->write("\x0D");
            // Erase line.
            $this->output->write("\x1B[2K");
        }
    }

    /**
     * Hide terminal cursor if the terminal supports escape sequences.
     *
     * @return void
     */
    public function hideCursor()
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[?25l");
        }
    }

    /**
     * Unhide terminal cursor if the terminal supports escape
     * sequences and assuming the cursor is currently hidden.
     *
     * @return void
     */
    public function unhideCursor()
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[?25h");
        }
    }

    /**
     * Erase previous terminal line if the terminal supports escape sequences.
     *
     * @return void
     */
    public function erasePreviousLine()
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[1A\e[K");
        }
    }

    /**
     * Get a cached value from the cache array or set it in cache with the given callback.
     *
     * @param  string  $key
     * @param  Closure|null  $createWith
     * @return mixed
     */
    public function fromArrayCache(string $key, ?Closure $createWith = null)
    {
        if (! $this->hasArrayCacheValue($key)) {
            return  $this->cache[$key] = $createWith();
        }

        return $this->cache[$key] ?? null;
    }

    /**
     * Get a cached value from the cache array.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasArrayCacheValue(string $key)
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Return a blade engine for rendering textual files.
     *
     * @return \Surgiie\Blade\Blade
     */
    protected function blade(): Blade
    {
        $blade = $this->fromArrayCache('blade', fn () => new Blade(
            container: $this->laravel,
            filesystem: new Filesystem,
        ));

        // if a compiled path is set on the application, set it.
        $compilePath = config('app.compiled_path');

        if (! is_null($compilePath)) {
            $blade->setCompiledPath($compilePath);
        }

        return $blade;
    }

    /**
     * Compile a textual file with blade using the given path and data.
     *
     * @param  string  $path
     * @param  array  $data
     * @param  bool  $removeCompiledDirectory
     * @return string
     */
    public function compile(string $path, array $data = [], bool $removeCompiledDirectory = false): string
    {
        $blade = $this->blade();
        $result = $blade->compile($path, $data);

        if ($removeCompiledDirectory) {
            (new Filesystem)->deleteDirectory($blade->getCompiledPath());
        }

        return $result;
    }

    /**
     * Output a message using the view console components.
     *
     * @param  string  $title
     * @param  string  $content
     * @param  string  $bg
     * @param  string  $fg
     * @return void
     */
    public function message(string $title, string $content, string $bg = 'gray', string $fg = 'white')
    {
        $this->consoleView('line', [
            'bgColor' => $bg,
            'marginTop' => ($this->output instanceof NewLineAware && $this->output->newLineWritten()) ? 0 : 1,
            'fgColor' => $fg,
            'title' => $title,
            'content' => $content,
        ]);
    }

    /**
     * Print a debug view console component message if a debug option exists on the command.
     *
     * @param  string  $message
     * @param  bool  $clearLine
     * @return void
     */
    protected function debug(string $message, bool $clearLine = false)
    {
        if ($this->hasOption('debug') && $this->data->get('debug')) {
            if ($clearLine) {
                $this->clearTerminalLine();
            }
            $this->message('DEBUG', $message, 'yellow', 'black');
        }
    }

    /**
     * Return the merged options and arguments data collection or a value from it if a key is passed.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return \Illuminate\Support\Collection|mixed
     */
    public function getData(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->data;
        }

        return $this->data->get($key, $default);
    }

    /**
     * Return the arbitrary data collection or a value from it if a key is passed.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return \Illuminate\Support\Collection|mixed
     */
    public function getArbitraryData(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->arbitraryData;
        }

        return $this->arbitraryData->get($key, $default);
    }

    /**
     * Check if the pctnl extension is loaded.
     *
     * @return bool
     */
    public function pctnlIsLoaded(): bool
    {
        return extension_loaded('pcntl');
    }

    /**Run a new command task.*/
    public function runTask(string $title = '', $task = null, string $finishedText = '')
    {
        $task = $this->laravel->make(Task::class, ['title' => $title, 'command' => $this, 'callback' => $task]);

        if (static::$concurrentTasks == false || ! $this->pctnlIsLoaded()) {
            $task->runNonConcurrently();
        } else {
            $task->run();
        }

        $finishedText = $finishedText ?: $title;
        $this->output->writeln(
            "  $finishedText: ".($task->succeeded() ? '<info>✓</info>' : '<error>failed</error>')
        );

        return $task;
    }

    /**
     * Parse an array input interface for tokens.
     *
     * This will only be the case when running tests.
     *
     * @param  InputInterface  $input
     * @return array
     */
    protected function parseArrayInputInterfaceTokens(InputInterface $input): array
    {
        $tokens = [];

        foreach (invade($input)->parameters as $name => $value) {
            if (is_array($value) && str_starts_with($name, '--')) {
                foreach ($value as $v) {
                    $tokens[] = "$name=$v";
                }
            } elseif (str_starts_with($name, '--')) {
                $tokens[] = is_bool($value) ? "$name" : "$name=$value";
            }
        }

        return $tokens;
    }

    /**
     * Check if an option was passed in the terminal/command call.
     *
     * @param  string  $name
     * @return bool
     */
    protected function optionWasPassed(string $name): bool
    {
        $name = ltrim($name, '--');

        return str_contains($this->commandTokensString, $name);
    }

    /**
     * Initialize the command for execution.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        try {
            $tokens = invade($input)->tokens;
        } catch (ReflectionException) {
            if ($input instanceof ArrayInput) {
                $tokens = $this->parseArrayInputInterfaceTokens($input);
            }
        }

        $this->commandTokensString = implode(' ', $tokens);
        // parse arbitrary options if set.
        if ($this->fromPropertyOrMethod('arbitraryOptions', false)) {
            $parser = new OptionsParser($tokens);
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

        $mergedData = array_merge($this->arguments(), $this->options());

        if ($this->castDatesToCarbon) {
            try {
                $mergedData = (new DataTransformer($mergedData, ['*date*' => ['?', Carbon::class]]))->transform();
            } catch (InvalidFormatException $e) {
                $this->exit($e->getMessage());
            }
        }

        $this->data = collect($mergedData)->filter(function ($v) {
            return ! is_null($v);
        });
    }

    /**
     * Ask for input with the given name or ask user for it if empty.
     *
     * @param  string  $name
     * @param  array  $options
     * @return string
     */
    protected function getOrAskForInput(string $name, array $options = [])
    {
        $input = $this->data->get($name);

        if (! empty($input)) {
            return $input;
        }

        $rules = $options['rules'] ?? [];
        $messages = $options['messages'] ?? [];
        $attributes = $options['attributes'] ?? [];
        $transformers = $options['transformers'] ?? [];
        $transformersAfterValidation = $options['transformersAfterValidation'] ?? [];

        $secret = $options['secret'] ?? false;
        $confirm = $options['confirm'] ?? false;

        $validate = function ($input) use ($name, $rules, $messages, $attributes) {
            if ($this->classUsesTrait($this, WithValidation::class)) {
                $validator = $this->validator([$name => $input], [$name => $rules], $messages, $attributes);

                if ($validator->fails()) {
                    $this->displayValidationErrors($validator, isUserInput: true);
                    $this->exit();
                }
            }
        };
        $method = $secret ? 'secret' : 'ask';
        $label = $options['label'] ?? str_replace(['_', '-'], [' ', ' '], $name);

        $message = "Enter $label:";
        $this->message('INPUT', $message, fg: 'white', bg: 'green');
        $input = $this->$method('ctrl-c to exit');

        $input = $this->transform($input, $transformers);

        $validate($input);

        $input = $this->transform($input, $transformersAfterValidation);

        if ($confirm) {
            $this->message('CONFIRM INPUT', "Confirm $label:", fg: 'black', bg: 'cyan');
            $confirmInput = $this->$method('ctrl-c to exit');

            while ($input != $confirmInput) {
                $this->message('CONFIRM FAILED', "Try $label confirmation again", fg: 'white', bg: 'red');
                $confirmInput = $this->$method('ctrl-c to exit');
            }
        }

        $this->data->put($name, $input);

        return $input;
    }

    /**
     * Check if a dependency is installed or if the given requirement passes..
     *
     * @throws \Surgiie\Console\Exceptions\FailedRequirementException
     */
    protected function checkRequirement($requirement)
    {
        $isString = is_string($requirement);

        if (is_callable($requirement)) {
            $error = $requirement();
        } elseif ($isString && method_exists($this, $requirement)) {
            $error = $this->laravel->call([$this, $requirement]);
        } elseif ($isString && class_exists($requirement)) {
            $instance = $this->laravel->make($requirement);
            $error = $this->laravel->call($instance);
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
        try {
            $status = 0;

            // check requirements as defined by developer
            foreach ($this->fromPropertyOrMethod('requirements', []) as $requirement) {
                try {
                    $this->checkRequirement($requirement);
                } catch (FailedRequirementException $e) {
                    $this->exit($e->getMessage());
                }
            }

            // transform the data before validation
            if (($withTransformers = $this->classUsesTrait($this, WithTransformers::class)) && $transformers = $this->transformers()) {
                $this->data = collect(
                    $this->transformData($this->data->all(), $transformers)
                );
            }

            // validate the data.
            $this->validate();

            // post validation transformation
            if ($withTransformers && $transformers = $this->transformersAfterValidation()) {
                $this->data = collect(
                    $this->transformData($this->data->all(), $transformers)
                );
            }

            $this->data = $this->data->filter(function ($value, $name) {
                $isArbitrary = $this->arbitraryData->has($name);

                // update the value in case it has gone through transformation
                if ($isArbitrary) {
                    $this->arbitraryData->put($name, $value);
                }

                return ! $isArbitrary;
            });

            // run the command
            $ms = Benchmark::measure(function () use (&$status) {
                $status = $this->executeCommand();
            });

            return $status;
        } catch (ExitCommandException $e) {
            $level = $e->getLevel();

            $message = $e->getMessage();
            
            if($message){
                $this->components->$level($e->getMessage());
            }
            
            return $e->getStatus();
        }
    }

    /**
     * Validate the current data for options and arguments.
     *
     * @return void
     */
    protected function validate(): void
    {
        if ($this->classUsesTrait($this, WithValidation::class)) {
            $validator = $this->validator($this->data->all());

            if ($validator->fails()) {
                $this->displayValidationErrors($validator);
                $this->exit();
            }
        }
    }

    /**
     * Display the validation errors that exist on the given validator.
     *
     * @param  Validator  $validator
     * @param  bool  $isUserInput
     * @return void
     */
    protected function displayValidationErrors(Validator $validator, bool $isUserInput = false)
    {
        foreach ($validator->messages()->getMessages() as $name => $errors) {
            $isOption = $this->hasOption($name);
            $isArgument = $this->hasArgument($name);

            if ($isUserInput) {
                $name = str_replace(['-', '_'], [' ', ' '], $name);
                $type = '';
            } elseif (! $isOption && ! $isArgument) {
                $name = '--'.$name;
                $type = 'option';
            } else {
                $name = $isOption ? '--'.$name : $name;
                $type = $isOption ? 'option' : 'argument';
            }

            $this->components->error(str_replace([':name', ':type'], [$name, $type], $errors[0]));
        }
    }

    /**
     * Execute the command.
     *
     * @return int
     */
    private function executeCommand(): int
    {
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        return (int) $this->laravel->call([$this, $method]);
    }
}
