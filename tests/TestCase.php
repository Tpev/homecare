<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // General behavior suites explicitly simulate a disabled/public rollout.
        // Focused rollout tests opt in below this baseline; the standalone
        // Laravel warmup tests the actual public defaults and preserved pauses,
        // both uncached and cached.
        $app['config']->set([
            'care_hours.enabled' => false,
            'care_hours.gifts_enabled' => false,
            'care_hours.pilot_only' => false,
        ]);

        return $app;
    }
}
