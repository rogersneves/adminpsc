<?php

namespace Modules\Scheduling\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Scheduling\Models\Session;
use Modules\Scheduling\Models\WaitingListEntry;
use Modules\Scheduling\Policies\SessionPolicy;
use Modules\Scheduling\Policies\WaitingListEntryPolicy;

class SchedulingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Scheduling';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'scheduling';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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

        Gate::policy(Session::class, SessionPolicy::class);
        Gate::policy(WaitingListEntry::class, WaitingListEntryPolicy::class);
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
