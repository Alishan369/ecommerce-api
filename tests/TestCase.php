<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real service (Razorpay etc.). Tests that need HTTP fake it explicitly.
        Http::preventStrayRequests();
    }
}
