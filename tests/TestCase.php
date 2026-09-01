<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the throwaway SQLite database.
     *
     * phpunit.xml sets DB_CONNECTION=sqlite and DB_DATABASE=:memory:, but those
     * are env() overrides — and `php artisan config:cache` freezes config, after
     * which env() is never consulted again. A cached config therefore silently
     * hands the test suite the DEVELOPMENT MySQL connection, and the first test
     * using RefreshDatabase runs migrate:fresh against it, which drops every
     * table. That is not hypothetical: it destroyed this project's local
     * database on 2026-09-01.
     *
     * The stale cache is invisible from inside a test run, so the check has to
     * happen here, before any migration is allowed to touch a connection.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || ! in_array($database, [':memory:', ''], true)) {
            throw new RuntimeException(
                "Refusing to run tests against '{$connection}' database '{$database}'. ".
                'Tests must use the in-memory SQLite connection from phpunit.xml. '.
                'This almost always means a cached config is overriding it — run '.
                '`php artisan config:clear` and try again.'
            );
        }
    }
}
