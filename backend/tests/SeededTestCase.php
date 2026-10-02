<?php

namespace Tests;

use App\Models\Road;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Migrates and seeds (reference + demo data) once per run; each test runs in a
 * rolled-back transaction.
 */
abstract class SeededTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function userByEmail(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function road(string $code): Road
    {
        return Road::where('code', $code)->firstOrFail();
    }
}
