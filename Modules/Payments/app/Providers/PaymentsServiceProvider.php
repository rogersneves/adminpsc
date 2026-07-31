<?php

namespace Modules\Payments\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\Gateways\PaymentGatewayManager;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Gateway de pagamento (marco): o driver ativo (config payments.default) é
        // resolvido pelo Manager; quem depende de PaymentGatewayInterface recebe ele.
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->bind(
            PaymentGatewayInterface::class,
            fn ($app) => $app->make(PaymentGatewayManager::class)->driver(),
        );
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Payments';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payments';

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
