<?php

namespace Modules\Notifications\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Notifications\Console\Commands\SendChargeReminders;
use Modules\Notifications\Console\Commands\SendSessionReminders;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Notifications';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'notifications';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendSessionReminders::class,
        SendChargeReminders::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(SendSessionReminders::class)->hourly();
        $schedule->command(SendChargeReminders::class)->daily();
    }
}
