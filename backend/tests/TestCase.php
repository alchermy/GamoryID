<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep the test run from writing to the real per-concern log files
        // (discord/billing/imports/scheduler), which are named channels and so
        // bypass the null default configured in phpunit.xml.
        config([
            'logging.default' => 'null',
            'logging.channels.discord.driver' => 'null',
            'logging.channels.billing.driver' => 'null',
            'logging.channels.imports.driver' => 'null',
            'logging.channels.scheduler.driver' => 'null',
        ]);
    }
}
