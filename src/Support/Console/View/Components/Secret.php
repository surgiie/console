<?php

namespace Surgiie\Console\Support\Console\View\Components;

use Illuminate\Console\View\Components\Component;

class Secret extends Component
{
    public function render($question, $default = null)
    {
        return $this->usingQuestionHelper(fn () => $this->output->askHidden($question, $default));
    }
}
