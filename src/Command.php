<?php

namespace Surgiie\Console;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Contracts\NewLineAware;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command as LaravelZeroCommand;
use ReflectionException;
use Surgiie\Blade\Blade;
use Surgiie\Console\Concerns\FromPropertyOrMethod;
use Surgiie\Console\Exceptions\ExitException;
use Surgiie\Console\Exceptions\FailedRequirementException;
use Surgiie\Console\Support\Console\View\Factory;
use Surgiie\Console\Support\Task;
use Surgiie\Transformer\Concerns\UsesTransformer;
use Surgiie\Transformer\DataTransformer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
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
     */
    protected Collection $data;

    /**
     * Cached array for saving values repeatedly called.
     */
    protected array $cache = [];

    /**
     * The command tokens passed in the terminal as a string.
     */
    protected string $commandTokensString = '';

    /**
     * The options that were arbitrary.
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
     * @return \Surgiie\Console\Support\Console\View\Factory;
     */
    public function consoleViewComponents()
    {
        return $this->components;
    }

    /**
     * Check if a class uses a given trait.
     *
     * @param  mixed  $class
     */
    protected function classUsesTrait($class, string $trait): bool
    {
        return once(fn () => array_key_exists($trait, class_uses($class)));
    }

    /**
     * Throw an ExitException.
     *
     * @throws \Surgiie\Console\Exceptions\ExitException
     */
    protected function exit(string $error = '', int $code = 1, string $level = 'error'): void
    {
        throw new ExitException($error, $code, $level);
    }

    /**
     * The transformer or casts to run on command data.
     */
    protected function transformers(): array
    {
        return [];
    }

    /**
     * The transformer or casts to run on command data after validation.
     */
    protected function transformersAfterValidation(): array
    {
        return [];
    }

    /**
     * Renders a console view with termwind renderer.
     */
    protected function consoleView(string $view, array $data, int $verbosity = OutputInterface::VERBOSITY_NORMAL): void
    {
        renderUsing($this->output);

        $view = rtrim($view, '.php');
        $path = base_path("resources/views/console/$view.php");

        if (! is_file($path)) {
            $path = __DIR__."/resources/views/$view.php";
        }

        render((string) $this->compile($path, $data, removeCachedFile: true), $verbosity);
    }

    /**
     * Get the output style instance.
     */
    public function getOutputStyle(): OutputStyle
    {
        return $this->output;
    }

    /**
     * Clear terminal line if the terminal supports escape sequences.
     */
    public function clearTerminalLine(): void
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
     */
    public function hideCursor(): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[?25l");
        }
    }

    /**
     * Unhide terminal cursor if the terminal supports escape
     * sequences and assuming the cursor is currently hidden.
     */
    public function unhideCursor(): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[?25h");
        }
    }

    /**
     * Erase previous terminal line if the terminal supports escape sequences.
     */
    public function erasePreviousLine(): void
    {
        if ($this->output->isDecorated()) {
            $this->output->write("\e[1A\e[K");
        }
    }

    /**
     * Get a cached value from the cache array or set it in cache with the given callback.
     *
     * @param  \Closure  $createWith
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
     */
    public function hasArrayCacheValue(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Check if the current os is windows.
     */
    public function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Return path compiled directory used for the blade engine.
     */
    protected function bladeCompiledPath(): string
    {
        // use tests directory when running unit tests in laravel/laravel-zero apps.
        if (property_exists($this, 'app') && $this->app->runningUnitTests()) {
            return base_path('tests/.compiled');
        }

        // otherwirse use the tmp directory.
        if ($this->isWindows()) {
            $tmp = getenv('TEMP');
        } else {
            $tmp = '/tmp';
        }

        return rtrim($tmp, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.compiled';
    }

    /**
     * Return a blade engine for rendering textual files.
     */
    protected function blade(): Blade
    {
        $blade = $this->fromArrayCache('blade', fn () => new Blade(
            container: $this->laravel,
            filesystem: new Filesystem,
        ));

        $compilePath = $this->bladeCompiledPath();

        $blade->setCompiledPath($compilePath);

        return $blade;
    }

    /**
     * Compile a textual file with blade using the given path and data.
     */
    public function compile(string $path, array $data = [], bool $removeCachedFile = false): string
    {
        $blade = $this->blade();

        $result = $blade->compile($path, $data, removeCachedFile: $removeCachedFile);

        return $result;
    }

    /**
     * Output a message using the view console components.
     *
     * @return void
     */
    public function message(string $title, string $content, string $bg = 'gray', string $fg = 'white')
    {
        Blade::dontCacheCompiled();

        $this->consoleView('line', [
            'bgColor' => $bg,
            'marginTop' => ($this->output instanceof NewLineAware && $this->output->newLineWritten()) ? 0 : 1,
            'fgColor' => $fg,
            'title' => $title,
            'content' => $content,
        ]);

        Blade::cacheCompiled();
    }

    /**
     * Print a debug view console component message if a debug option exists on the command.
     */
    protected function debug(string $message, bool $clearLine = false): void
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
     * @param  mixed  $default
     * @return mixed
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
     * @param  mixed  $default
     * @return mixed
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
     */
    public function pctnlIsLoaded(): bool
    {
        return extension_loaded('pcntl');
    }

    /**
     * Run a new command task.
     */
    public function runTask(string $title = '', Closure $task = null, string $finishedText = ''): Task
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
     * Check if an option was passed in the command call.
     */
    protected function optionWasPassed(string $name): bool
    {
        $name = ltrim($name, '--');

        return str_contains($this->commandTokensString, $name);
    }

    /**
     * Initialize the command for execution.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        try {
            $tokens = invade($input)->tokens;
        } catch (ReflectionException) {
            if ($input instanceof StringInput) {
                $input = new ArrayInput(explode(' ', trim(strval($input), "'")));
            }
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
    }

    /**
     * Ask for input with the given name or ask user for it if empty.
     */
    protected function getOrAskForInput(string $name, array $options = []): string
    {
        $input = $this->data->get($name);

        if (! empty($input)) {
            return $input;
        }

        $secret = $options['secret'] ?? false;
        $confirm = $options['confirm'] ?? false;

        $method = $secret ? 'secret' : 'ask';
        $label = $options['label'] ?? str_replace(['_', '-'], [' ', ' '], $name);

        $input = $this->components->$method("What is the $label?");

        $input = $this->transform($input, $options['transformers'] ?? []);

        $this->validate(
            data: [$name => $input],
            rules: [$name => $options['rules'] ?? []],
            messages: $options['messages'] ?? [],
            attributes: $options['attributes'] ?? [],
            isUserInput: true
        );

        if ($confirm) {
            $confirmInput = $this->components->$method("Confirm $label:");

            while ($input != $confirmInput) {
                $confirmInput = $this->components->$method('Confirmation doesnt match, try again:');
            }
        }

        $input = $this->transform($input, $options['transformersAfterValidation'] ?? []);

        $this->data->put($name, $input);

        return $input;
    }

    /**
     * Check if a dependency is installed or if the given requirement passes..
     *
     * @param  mixed  $requirement
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
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);

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
            $this->data = collect(
                $this->transformData($this->data->all(), $this->transformers())
            );

            // validate the data.
            $this->validate($this->data->all());

            // post validation transformation
            $this->data = collect(
                $this->transformData($this->data->all(), $this->transformersAfterValidation())
            );

            $this->data = $this->data->filter(function ($value, $name) {
                $isArbitrary = $this->arbitraryData->has($name);

                // update the value in case it has gone through transformation
                if ($isArbitrary) {
                    $this->arbitraryData->put($name, $value);
                }

                return ! $isArbitrary;
            });

            // run the command
            $status = $this->executeCommand();
        } catch (ExitException $e) {
            $level = $e->getLevel();

            $message = $e->getMessage();

            if ($message) {
                $this->components->$level($e->getMessage());
            }

            $status = $e->getStatus();
        }

        if ($status == 0 && method_exists($this, 'succeeded')) {
            $this->laravel->call([$this, 'succeeded']);
        }
        if ($status >= 1 && method_exists($this, 'failed')) {
            $this->laravel->call([$this, 'failed']);
        }

        return $status;
    }

    /**
     * Interact with the user before validating the input.
     *
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

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
     * Get the path to directory holding the lang file for validation.
     */
    protected function getValidationLangPath(): string
    {
        return __DIR__.'/resources/lang';
    }

    /**
     * Get the locale to use for validation.
     */
    protected function getValidationLangLocale(): string
    {
        return 'en';
    }

    /**
     * Create a new validator instance.
     *
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $attributes
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

    /**
     * Validate the current data for options and arguments.
     */
    protected function validate(array $data, ?array $rules = null, ?array $messages = null, ?array $attributes = null, bool $isUserInput = false): void
    {
        $validator = $this->validator($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $this->displayValidationErrors($validator, $isUserInput);
            $this->exit();
        }
    }

    /**
     * Display the validation errors that exist on the given validator.
     *
     *
     * @return void
     */
    protected function displayValidationErrors(Validator $validator, bool $isUserInput = false)
    {
        foreach ($validator->messages()->getMessages() as $name => $errors) {
            $isOption = $this->hasOption($name);
            $isArgument = $this->hasArgument($name);

            $type = 'option';

            if ($isUserInput) {
                $name = str_replace(['-', '_'], [' ', ' '], $name);
                $type = 'input';
            } elseif ($isOption || $this->arbitraryData->has($name)) {
                $name = '--'.$name;
            } elseif ($isArgument) {
                $type = 'argument';
            }

            $this->components->error(str_replace([':name', ':type'], [$name, $type], $errors[0]));
        }
    }

    /**
     * Execute the command.
     */
    private function executeCommand(): int
    {
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        return (int) $this->laravel->call([$this, $method]);
    }
}
