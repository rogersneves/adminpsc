<?php

namespace Modules\Security\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Security\Console\Commands\AnonymizePatientCommand;
use Modules\Security\Console\Commands\GenerateMasterKeyCommand;
use Modules\Security\Console\Commands\RotateEncryptionKeyCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

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
        RotateEncryptionKeyCommand::class,
        AnonymizePatientCommand::class,
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
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
