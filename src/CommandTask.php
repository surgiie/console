<?php

namespace Surgiie\Console;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Spatie\Fork\Fork;

class CommandTask
{
    /**The title of the task.*/
    protected string $title;

    /** The command running this task. */
    protected Command $command;

    /**The task function we are executing.*/
    protected Closure $callback;

    /**The console ouptut.*/
    protected OutputStyle $output;

    /**Persisted data from task. */
    protected $persistedData = [];

    /**Whether the task was succesful.*/
    protected bool $succesful = false;

    /**The spinner frames to use for spinner.*/
    protected static array $spinnerFrames = [
        '⠏',
        '⠛',
        '⠹',
        '⢸',
        '⣰',
        '⣤',
        '⣆',
        '⡇',
    ];

    public function __construct(string $title, Command $command, Closure $callback)
    {
        $this->title = $title;
        $this->command = $command;
        $this->callback = $callback;
        $this->id = Str::uuid();
        $this->output = $command->getConsoleOutput();
    }

    /**Set data that should persist from child process.*/
    public function persist($data): static
    {
        $this->persistedData = $data;

        return $this;
    }

    /**Get the persisted data.*/
    public function getData()
    {
        return $this->persistedData;
    }

    /**Whether the task was successful.*/
    public function succeeded(): bool
    {
        return $this->succesful;
    }

    /**Get the command instance to write a new line in task.*/
    public function command()
    {
        $this->clearTerminalLine();

        return $this->command;
    }

    /**Get the components instance to write a new line in task.*/
    public function components()
    {
        $this->clearTerminalLine();

        return $this->command->getComponents();
    }

    /**Run the task callback */
    public function run()
    {
        $results = Fork::new()
            ->before(fn () => $this->saveTaskFile())
            ->run(
                $this->spin(),
                function (): bool {
                    $callback = $this->callback;
                    $result = $callback($this);

                    $this->cleanup();

                    file_put_contents($this->taskFilePath().'.state', serialize($this->persistedData));

                    return $result;
                }
            );

        $this->succesful = $results[1];
        $this->output->writeln(
            'Finished - ['.$this->title.']: '.($results[1] ? '<info>✓</info>' : '<error>failed</error>')
        );

        $this->persistedData = unserialize(file_get_contents($stateFile = $this->taskFilePath().'.state'));
        unlink($stateFile);

        return $this;
    }

    /**
     * Remove the run file.
     *
     * @return bool
     */
    protected function cleanup(): bool
    {
        return @unlink($this->taskFilePath());
    }

    /**
     * Determine whether the spinner is spinning and should continue.
     *
     * @return bool
     */
    protected function isRunning(): bool
    {
        return file_exists($this->taskFilePath());
    }

    /**Set the frames to use for loader spinner.*/
    public function setSpinnerFrames(array $frames): void
    {
        static::$spinnerFrames = $frames;
    }

    /**
     * Start the spinner and keep going until we can detect in the
     * state that it should stopped.
     *
     * @param  string  $outputText
     * @return callable
     */
    protected function spin(): callable
    {
        return function () {
            while ($this->isRunning()) {
                foreach (static::$spinnerFrames as $frame) {
                    $this->clearTerminalLine();
                    $this->output->write($frame.' '.$this->title);
                    usleep(100000);
                }
            }
            $this->clearTerminalLine();
        };
    }

    protected function taskFilePath()
    {
        return storage_path('app/console-tasks/'.$this->id);
    }

    /**
     * Save the file that signifies if we are running the task.
     *
     * @return bool
     */
    protected function saveTaskFile(): bool
    {
        @mkdir(dirname($path = $this->taskFilePath()), recursive: true);

        return touch($path);
    }

    /**Clear console line.*/
    protected function clearTerminalLine()
    {
        if ($this->output->isDecorated()) {
            // Move the cursor to the beginning of the line
            $this->output->write("\x0D");
            // Erase line.
            $this->output->write("\x1B[2K");
        } else {
            $this->output->writeln(''); // Make sure we first close the previous line
        }
    }
}
