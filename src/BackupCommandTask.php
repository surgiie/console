<?php

namespace Surgiie\Console;

use Surgiie\Console\Support\Task;

class BackupCommandTask extends Task
{
    /**Run the task callback */
    public function run()
    {
        $callback = $this->callback;

        $this->output->write($this->title.': <comment>loading...</comment>');

        $this->succesful = $callback($this);

        $this->clearTerminalLine();

        return $this;
    }
}
