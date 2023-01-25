<?php

namespace Surgiie\Console\Support;

use Closure;
use Illuminate\Support\Str;
use Phar;
use Spatie\Fork\Fork;
use Surgiie\Console\Command;

class Task
{
    /**
     * The title of the task for the console output.
     *
     * @var string
     */
    protected string $id;

    /**
     * The id of the task.
     *
     * @var string
     */
    protected string $title;

    /**
     * The spinner characters to use for loader.
     *
     * @var array
     */
    protected static array $spinnerFrames = [
        '⠇',
        '⠋',
        '⠙',
        '⠸',
        '⠴',
        '⠦',
    ];

    /**
     * The command instance that is runnning this task.
     *
     * @var Surgiie\Console\Command
     */
    protected Command $command;

    /**
     * The task function being executed.
     *
     * @var Closure
     */
    protected Closure $callback;

    /**
     * Data that is persisted from the task when running
     * task concurrently with the spatie/fork package.
     *
     * @var array
     */
    protected array $taskData = [];

    /**
     * Whether the task was succesful.
     *
     * @var bool
     */
    protected bool $succesful = false;

    /**
     * Construct a new Task instance.
     *
     * @param  string  $title
     * @param  \Surgiie\Console\Command  $command
     * @param  \Closure  $callback
     */
    public function __construct(string $title, Command $command, Closure $callback)
    {
        $this->title = $title;
        $this->command = $command;
        $this->callback = $callback;
        $this->id = Str::uuid();
    }

    /**
     * Remember data when running concurrently.
     *
     * @param  array  $data.
     * @return static
     */
    public function remember(array $data): static
    {
        $this->taskData = array_merge($this->taskData, $data);

        return $this;
    }

    /**
     * The uuid of the task.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the remembered data.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->taskData;
    }

    /**
     * Check if the task succeeded.
     *
     * @return bool
     */
    public function succeeded(): bool
    {
        return $this->succesful === null || $this->succesful === true;
    }

    /**
     * Run the task non concurrently.
     *
     * @return static
     */
    public function runNonConcurrently()
    {
        $output = $this->command->getOutputStyle();

        $callback = $this->callback;

        $output->write($this->title.': <comment>loading...</comment>');

        $result = $callback($this);

        $this->succesful = is_null($result) ? true : $result;

        if ($output->isDecorated()) {
            $this->command->clearTerminalLine();
        } else {
            $output->writeln('');
        }

        return $this;
    }

    /**
     * Run the task concurrently.
     *
     * @return static
     */
    public function run()
    {
        $output = $this->command->getOutputStyle();

        $output->writeln('  Running: '.$this->title);

        $this->startConcurrentRun();

        $results = Fork::new()
            ->run(
                // show a spinner in parent process
                function () use ($output) {
                    $this->command->hideCursor();

                    while ($this->runningConcurrently()) {
                        foreach (static::$spinnerFrames as $frame) {
                            $this->command->clearTerminalLine();

                            $output->write('  '.$frame);

                            usleep(100000);
                        }
                    }

                    $this->command->unhideCursor();

                    $this->command->clearTerminalLine();
                },
                // while child process executes the callback.
                function () {
                    try {
                        $callback = $this->callback;

                        $result = $callback($this);

                        $this->removeConcurrentTaskFile();

                        $this->writeStateFile($this->taskData);

                        return $result;
                    } catch(\Throwable $e) {
                        $this->removeConcurrentTaskFile();
                        $this->removeConcurrentTaskStateFile();

                        throw $e;
                    }
                }
            );

        $this->command->erasePreviousLine();

        $this->succesful = is_null($results[1]) ? true : $results[1];

        $this->removeConcurrentTaskStateFile();

        return $this;
    }

    /**
     * Write task state file with the given data.
     *
     * @param  array  $data
     * @return int
     */
    protected function writeStateFile(array $data = []): int
    {
        return file_put_contents($this->taskStateFilePath(), serialize($data));
    }

    /**
     * Remove concurrent task flag file.
     *
     * @return bool
     */
    protected function removeConcurrentTaskFile(): bool
    {
        return @unlink($this->taskFlagFilePath());
    }

    /**
     * Remove concurrent task state file.
     *
     * @return bool
     */
    protected function removeConcurrentTaskStateFile(): bool
    {
        $stateFile = $this->taskStateFilePath();

        if (is_file($stateFile)) {
            $this->taskData = unserialize(file_get_contents($stateFile));

            return unlink($stateFile);
        }

        return false;
    }

    /**
     * Determine whether the concurrent task is still running via flag file.
     *
     * @return bool
     */
    protected function runningConcurrently(): bool
    {
        return file_exists($this->taskFlagFilePath());
    }

    /**
     * Set the characters to use for loader spinner
     *
     * @param  array  $frames
     * @return void
     */
    public static function setSpinnerFrames(array $frames): void
    {
        static::$spinnerFrames = $frames;
    }

    /**
     * Return path to the task flag file.
     *
     * @return string
     */
    protected function taskFlagFilePath(): string
    {
        if ($phar = Phar::running(false)) {
            $base = dirname($phar).'/console-tasks/';
            @mkdir($base, recursive: true);

            return $base.$this->id;
        }

        return storage_path('app/console-tasks/'.$this->id);
    }

    /**
     * Return path to the task state file.
     *
     * @return string
     */
    protected function taskStateFilePath(): string
    {
        return $this->taskFlagFilePath().'.state';
    }

    /**
     * Save the file that signifies if we are starting the task.
     *
     * @return bool
     */
    private function startConcurrentRun(): bool
    {
        @mkdir(dirname($path = $this->taskFlagFilePath()), recursive: true);

        return touch($path);
    }
}
