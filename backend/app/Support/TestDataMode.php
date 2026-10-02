<?php

namespace App\Support;

/**
 * Request-scoped switch deciding whether `is_test` records are visible.
 *
 * Default comes from config (false in production). Authorised users can opt in
 * per session. Official exports call forceExclude(), which wins over everything.
 */
final class TestDataMode
{
    private ?bool $include = null;

    private bool $forcedExclude = false;

    /** Back to environment defaults (start of every request / job). */
    public function reset(): void
    {
        $this->include = null;
        $this->forcedExclude = false;
    }

    public function includesTestData(): bool
    {
        if ($this->forcedExclude) {
            return false;
        }

        return $this->include ?? (bool) config('rcd.test_data.include_by_default');
    }

    public function include(bool $include = true): void
    {
        $this->include = $include;
    }

    public function forceExclude(bool $force = true): void
    {
        $this->forcedExclude = $force;
    }

    /**
     * Run a callback with test data visible (used by seeders and admin tooling).
     */
    public function withTestData(callable $callback): mixed
    {
        $previous = $this->include;
        $this->include = true;

        try {
            return $callback();
        } finally {
            $this->include = $previous;
        }
    }
}
