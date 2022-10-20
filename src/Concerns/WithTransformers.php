<?php

namespace Surgiie\Console\Concerns;

use Surgiie\Transformer\Concerns\UsesTransformer;

trait WithTransformers
{
    use UsesTransformer;

    /**Specify transformers for arguments and options.*/
    protected function transformers()
    {
        return [];
    }

    /**Specify transformers for arguments and options after validation.*/
    protected function transformersAfterValidation()
    {
        return [];
    }
}
