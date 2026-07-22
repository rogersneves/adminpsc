<?php

namespace Modules\MedicalRecords\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\MedicalRecords\Policies\MedicalRecordPolicy;

class MedicalRecordsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'MedicalRecords';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'medicalrecords';

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

        // Não é Gate::policy — não existe um Model único "MedicalRecord"; a
        // autorização é sobre "este ator pode acessar o prontuário deste Patient",
        // registrada como habilidade nomeada (docs/06-Roadmap.md Fase 4).
        Gate::define('medicalRecords.view', [MedicalRecordPolicy::class, 'view']);
        Gate::define('medicalRecords.create', [MedicalRecordPolicy::class, 'create']);
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
