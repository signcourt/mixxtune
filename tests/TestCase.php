<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $environment = app()->environment();
        $database = (string) config('database.default');
        $databaseName = (string) config(
            "database.connections.{$database}.database"
        );

        if ($environment !== 'testing') {
            throw new RuntimeException(
                'Tests blocked: APP_ENV must be testing.'
            );
        }

        $forbiddenDatabases = [
            'bmtd_production',
            'production',
        ];

        if (
            in_array(
                strtolower($databaseName),
                $forbiddenDatabases,
                true
            )
        ) {
            throw new RuntimeException(
                'Tests blocked: production database detected.'
            );
        }
    }
}
