<?php

namespace BagherKeshmiri\PostmanSync\Tests;

use BagherKeshmiri\PostmanSync\PostmanSyncServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PostmanSyncServiceProvider::class];
    }
}
