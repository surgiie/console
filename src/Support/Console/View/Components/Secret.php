<?php

namespace Surgiie\Console\Support\Console\View\Components;

use Illuminate\Console\View\Components\Component;

class Secret extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $question
     * @param  string  $default
     * @return mixed
     */
    public function render($question, $default = null)
    {
        return $this->usingQuestionHelper(fn () => $this->output->askHidden($question, $default));
    }
}
