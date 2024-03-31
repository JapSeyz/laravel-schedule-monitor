<?php

namespace JapSeyz\ScheduleMonitor\Tests;

use CreateScheduleMonitorTables;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Factories\Factory;
use JapSeyz\ScheduleMonitor\ScheduleMonitorServiceProvider;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestKernel;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\Console\Output\BufferedOutput;
use function Termwind\renderUsing;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        TestKernel::clearScheduledCommands();
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'JapSeyz\\ScheduleMonitor\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        renderUsing(new BufferedOutput());
    }

    protected function getPackageProviders($app)
    {
        return [
            ScheduleMonitorServiceProvider::class,
        ];
    }

    protected function resolveApplicationConsoleKernel($app)
    {
        $app->singleton(Kernel::class, TestKernel::class);
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        include_once __DIR__ . '/../database/migrations/create_schedule_monitor_tables.php.stub';
        (new CreateScheduleMonitorTables())->up();
    }
}
