<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety: tests wipe the database, so never let them touch Neon.
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Tests must run on sqlite, never on the real database.');
        }
    }
}
