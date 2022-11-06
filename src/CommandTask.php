<?php

namespace Surgiie\Console;

use Spatie\Fork\Fork;
use Surgiie\Console\Support\Task;

class CommandTask extends Task
{
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

    /**Run the task callback*/
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

                    file_put_contents($this->taskFilePath().'.state', serialize($this->taskData));

                    return $result;
                }
            );

        $this->succesful = $results[1];

        $this->taskData = unserialize(file_get_contents($stateFile = $this->taskFilePath().'.state'));

        unlink($stateFile);

        return $this;
    }

    /**
     * Remove the run file.
     */
    protected function cleanup(): bool
    {
        return @unlink($this->taskFilePath());
    }

    /**
     * Determine whether the spinner is spinning and should continue.
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
     * Start the spinner and keep going until we can detect
     * when it should stopped by checking if the task file
     * is still present.
     */
    protected function spin(): callable
    {
        return function () {
            while ($this->isRunning()) {
                foreach (static::$spinnerFrames as $frame) {
                    $this->command->clearTerminalLine();
                    $this->output->write($frame.' '.$this->title);
                    usleep(100000);
                }
            }
            $this->command->clearTerminalLine();
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
}
