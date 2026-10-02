<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ExcludeTestDataScope;

/**
 * Records flagged `is_test` are hidden unless TestDataMode includes them.
 */
trait HasTestFlag
{
    public static function bootHasTestFlag(): void
    {
        static::addGlobalScope(new ExcludeTestDataScope);
    }

    public function initializeHasTestFlag(): void
    {
        $this->mergeCasts(['is_test' => 'boolean']);
    }
}
