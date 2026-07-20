<?php

namespace Modules\Authorization\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Authorization\Console\Commands\MakeSuperAdminCommand;
use Modules\Authorization\Policies\UserPolicy;
use Modules\Users\Models\User;

class AuthorizationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Authorization';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'authorization';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        MakeSuperAdminCommand::class,
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

    public function boot(): void
    {
        parent::boot();

        // Descoberta automática de Policy não funciona entre módulos diferentes
        // (User está em Modules\Users, UserPolicy em Modules\Authorization).
        Gate::policy(User::class, UserPolicy::class);
    }

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
