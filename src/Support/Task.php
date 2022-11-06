<?php

namespace Surgiie\Console\Support;

use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Surgiie\Console\Command;

abstract class Task
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
    protected array $taskData = [];

    /**Whether the task was succesful.*/
    protected bool $succesful = false;

    public function __construct(string $title, Command $command, Closure $callback)
    {
        $this->title = $title;
        $this->command = $command;
        $this->callback = $callback;
        $this->id = Str::uuid();
        $this->output = $command->getConsoleOutput();
    }

    /**Set data for the task */
    public function data($data): static
    {
        $this->taskData = $data;

        return $this;
    }

    /**Get the id of the task.*/
    public function getId()
    {
        return $this->id;
    }

    /**Get the task data.*/
    public function getData()
    {
        return $this->taskData;
    }

    /**Whether the task was successful.*/
    public function succeeded(): bool
    {
        return $this->succesful;
    }

    /**Run the task callback */
    abstract public function run();
}
