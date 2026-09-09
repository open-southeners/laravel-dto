<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Concerns\WithWorkbench;

use function Orchestra\Testbench\workbench_path;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench;

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(workbench_path('database/migrations'));
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // 'data-mapper' config is intentionally NOT set here: the package's
        // ServiceProvider merges its own defaults via mergeConfigFrom(), so
        // tests exercise the same unpublished-config path as consuming apps.
    }
}
