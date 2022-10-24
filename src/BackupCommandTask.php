<?php

namespace Surgiie\Console;

use Closure;
use Illuminate\Console\OutputStyle;

class BackupCommandTask
{
    /**The title of the task.*/
    protected string $title;

    /** The command running this task. */
    protected Command $command;

    /**The task function we are executing.*/
    protected Closure $callback;

    /**The console ouptut.*/
    protected OutputStyle $output;

    /**Whether the task was succesful.*/
    protected bool $succesful = false;

    public function __construct(string $title, Command $command, Closure $callback)
    {
        $this->title = $title;
        $this->command = $command;
        $this->callback = $callback;
        $this->output = $command->getConsoleOutput();
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

    /**Run the task callback */
    public function run()
    {
        $callback = $this->callback;

        $this->output->write($this->title.': <comment>loading...</comment>');

        $this->succesful = $callback($this);

        $this->clearTerminalLine();

        $this->output->writeln(
            'Finished - ['.$this->title.']: '.($this->succesful === true ? '<info>✓</info>' : '<error>failed</error>')
        );

        return $this;
    }
}
