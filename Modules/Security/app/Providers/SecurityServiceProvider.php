<?php

namespace Modules\Security\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Security\Console\Commands\GenerateMasterKeyCommand;

class SecurityServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Security';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'security';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        GenerateMasterKeyCommand::class,
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
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
