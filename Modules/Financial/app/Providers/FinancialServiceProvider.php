<?php

namespace Modules\Financial\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Financial\Console\Commands\ApplyLateChargeFees;
use Modules\Financial\Policies\FinancialPolicy;

class FinancialServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Financial';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'financial';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ApplyLateChargeFees::class,
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

        // Não é Gate::policy — a decisão é sobre "este ator pode ver/gerenciar o
        // financeiro deste Patient", não sobre uma instância única de FinancialCharge
        // (mesmo padrão de MedicalRecordPolicy, Fase 4).
        Gate::define('financial.view', [FinancialPolicy::class, 'view']);
        Gate::define('financial.manage', [FinancialPolicy::class, 'manage']);
    }

    /**
     * Define module schedules.
     *
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(ApplyLateChargeFees::class)->daily();
    }
}
