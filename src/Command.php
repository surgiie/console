<?php

namespace Surgiie\Console;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Contracts\NewLineAware;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use Laravel\Prompts\Spinner;
use LaravelZero\Framework\Commands\Command as LaravelZeroCommand;
use ReflectionException;
use Surgiie\Blade\Blade;
use Surgiie\Console\Concerns\FromPropertyOrMethod;
use Surgiie\Console\Exceptions\ExitException;
use Surgiie\Console\Exceptions\FailedRequirementException;
use Surgiie\Console\Support\Console\View\Factory;
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
     * The options and arguments merged together.
     */
    protected Collection $data;

    /**
     * Caching array to use for repeated calls.
     */
    protected array $cache = [];

    /**
     * The command line arguments as a string.
     */
    protected string $commandTokensString = '';

    /**
     * The options that are not defined on the command.
     */
    protected Collection $arbitraryData;

    /**
     * Auto cast dates to Carbon instances.
     */
    protected bool $castDatesToCarbon = true;

    /**
     * Constuct a new Command instance.
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
     * Return the console view components factory instances.
     */
    public function consoleViewComponents(): Factory
    {
        return $this->components;
    }

    /**
     * Determine if the class uses a trait.
     *
     * @param  string  $class
     */
    protected function classUsesTrait($class, string $trait): bool
    {
        return once(fn () => array_key_exists($trait, class_uses($class)));
    }

    /**
     * Throw an exception to exit the command.
     */
    protected function exit(string $error = '', int $code = 1, string $level = 'error'): void
    {
        throw new ExitException($error, $code, $level);
    }

    /**
     * The transformers to use on the data.
     *
     *
     * @see surgiie/transformer
     */
    protected function transformers(): array
    {
        return [];
    }

    /**
     * The transformers to use on the data after validation.
     *
     *
     * @see surgiie/transformer
     */
    protected function transformersAfterValidation(): array
    {
        return [];
    }

    /**
     * Renders a view/file for the console.
     */
    protected function consoleView(string $view, array $data, int $verbosity = OutputInterface::VERBOSITY_NORMAL): void
    {
        renderUsing($this->output);

        $view = rtrim($view, '.php');
        $path = base_path("resources/views/console/$view.php");

        if (! is_file($path)) {
            $path = __DIR__."/resources/views/$view.php";
        }

        render((string) $this->render($path, $data), $verbosity);
    }

    /**
     * Get the console output style instance.
     */
    public function getOutputStyle(): OutputStyle
    {
        return $this->output;
    }

    /**
     * Get a value out of the cache array or create a value with a closure.
     *
     * @return void
     */
    public function fromArrayCache(string $key, Closure $createWith = null)
    {
        if (! $this->hasArrayCacheValue($key)) {
            return $this->cache[$key] = $createWith();
        }

        return $this->cache[$key] ?? null;
    }

    /**
     * Check if the cache array has a value by key.
     */
    public function hasArrayCacheValue(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Check if the command is running on windows.
     */
    public function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Return the path to use for compiled blade files.
     */
    protected function bladeCompiledPath(): string
    {
        // use tests directory when running unit tests in laravel/laravel-zero apps.
        if (property_exists($this, 'app') && $this->app->runningUnitTests()) {
            return base_path('tests/.compiled');
        }

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.compiled';
    }

    /**
     * Return a blade instance.
     */
    protected function blade(): Blade
    {
        $blade = $this->fromArrayCache('blade', fn () => new Blade(
            container: $this->laravel,
        ));

        Blade::setCachePath($this->bladeCompiledPath());

        return $blade;
    }

    /**
     * Render a file using blade.
     *
     *
     * @deprecated version 4.1.0
     */
    public function compile(string $path, array $data = [], bool $cache = true): string
    {
        trigger_error('The compile method will be removed in a future release, use render method.', E_USER_WARNING);

        if (! $cache) {
            Blade::deleteCacheDirectory();
        }

        return $this->render($path, $data);
    }

    /**
     * Render a file using blade.
     */
    public function render(string $path, array $data = []): string
    {
        $blade = $this->blade();

        return $blade->render($path, $data);
    }

    /**
     * Print a message to console using console view components.
     *
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
     * Print a debug message if the command has a --debug option and it is passed.
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
     * Get a value from from the command data.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getData(string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->data;
        }

        return $this->data->get($key, $default);
    }

    /**
     * Get a value from from the command arbitrary data.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getArbitraryData(string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->arbitraryData;
        }

        return $this->arbitraryData->get($key, $default);
    }

    /**
     * Run a long running task with a spinner.
     *
     * @return bool|null
     */
    public function runTask(string $title = '', Closure $task = null, string $finishedText = '', bool $spinner = false)
    {
        $finishedText = $finishedText ?: $title;

        if ($spinner) {
            $result = (new Spinner($title))->spin(
                $task,
                $title,
            );
        } else {
            $result = invade((new Spinner($title)))->renderStatically($task);
        }

        $this->output->writeln(
            "  $finishedText: ".($result !== false ? '<info>Success</info>' : '<error>Failed</error>')
        );

        return $result;
    }

    /**
     * Parse the input tokens for an array input interface.
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
     * Check if an option was passed in a command call.
     */
    protected function optionWasPassed(string $name): bool
    {
        $name = ltrim($name, '--');

        return str_contains($this->commandTokensString, $name);
    }

    /**
     * Initialize the command input/ouput objects.
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
     * Check if a binary/executable is resolves in a `which` command.
     */
    protected function checkWhichPath(string $requirement): string
    {
        $process = (new Process(['which', $requirement]));

        $process->run();

        return $process->getOutput() == '' ? "This command requires $requirement." : '';
    }

    /**
     * Run the command with the given input and output.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output instanceof OutputStyle ? $output : $this->laravel->make(
            OutputStyle::class, ['input' => $input, 'output' => $output]
        );

        $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);

        if ($this->laravel instanceof Application) {
            $this->configurePrompts($input);
        }

        try {
            return \Symfony\Component\Console\Command\Command::run(
                $this->input = $input, $this->output
            );
        } finally {
            $this->untrap();
        }
    }

    /**
     * Check requirements defined on the class.
     *
     * @param  mixed  $requirement
     * @return void
     *
     * @throws InvalidArgumentException|FailedRequirementException
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
            $error = $this->checkWhichPath($requirement) ?: '';
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
     * @return void
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
                $isArbitrary = !$this->hasArgument($name) && $this->arbitraryData->has($name);

                // update the value in case it has gone through transformation
                if ($isArbitrary) {
                    $this->arbitraryData->put($name, $value);
                }else{
                    $this->arbitraryData->forget($name);
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
     * Setup the command before execution.
     *
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        $mergedData = array_merge($this->options(),$this->arguments());

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
     * Get the validation language files path.
     */
    protected function getValidationLangPath(): string
    {
        return __DIR__.'/resources/lang';
    }

    /**
     * Set the locale for the validation language files.
     */
    protected function getValidationLangLocale(): string
    {
        return 'en';
    }

    /**
     * Return a new Validator instance.
     */
    protected function validator(array $data, array $rules = null, array $messages = null, array $attributes = null): Validator
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
     * Validate the given data using the given rules.
     */
    protected function validate(array $data, array $rules = null, array $messages = null, array $attributes = null, string $type = 'option'): void
    {
        $validator = $this->validator($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $this->displayValidationErrors($validator, $type);
            $this->exit();
        }
    }

    /**
     * Display the given validator's error messages to the console.
     *
     * @return void
     */
    protected function displayValidationErrors(Validator $validator, string $type = 'option')
    {
        foreach ($validator->messages()->getMessages() as $name => $errors) {
            $isOption = $this->hasOption($name);
            $isArgument = $this->hasArgument($name);

            $parsedType = 'option';

            if ($type == 'input') {
                $name = str_replace(['-', '_'], [' ', ' '], $name);
                $parsedType = 'input';
            } elseif ($isOption || $this->arbitraryData->has($name)) {
                $name = '--'.$name;
            } elseif ($isArgument) {
                $parsedType = 'argument';
            }

            $this->components->error(str_replace([':name', ':type'], [$name, $parsedType], $errors[0]));
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
