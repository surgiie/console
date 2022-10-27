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

        $this->output->writeln(
            'Finished - ['.$this->title.']: '.($this->succesful === true ? '<info>✓</info>' : '<error>failed</error>')
        );

        return $this;
    }
}
