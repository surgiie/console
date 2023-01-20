<?php

namespace Surgiie\Console\Concerns;

use Surgiie\Transformer\Concerns\UsesTransformer;

trait WithTransformers
{
    use UsesTransformer;

    /**
     * The transformer or casts to run on command data.
     *
     * @return array
     */
    protected function transformers(): array
    {
        return [];
    }

    /**
     * The transformer or casts to run on command data after validation.
     *
     * @return array
     */
    protected function transformersAfterValidation()
    {
        return [];
    }
}
