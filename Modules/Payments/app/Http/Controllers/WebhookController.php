<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payments\Actions\HandleGatewayWebhookAction;
use Modules\Payments\Gateways\PaymentGatewayManager;

/**
 * Recebe webhooks do gateway de pagamento (marco). Público (o provedor chama sem
 * sessão) e isento de CSRF (ver bootstrap/app.php). Verifica a origem pelo driver,
 * normaliza o evento e concilia — de forma idempotente (HandleGatewayWebhookAction).
 */
class WebhookController extends Controller
{
    public function handle(
        string $driver,
        Request $request,
        PaymentGatewayManager $manager,
        HandleGatewayWebhookAction $action,
    ): JsonResponse {
        if (! array_key_exists($driver, (array) config('payments.gateways'))) {
            abort(404);
        }

        $gateway = $manager->driver($driver);

        if (! $gateway->verifyWebhook($request)) {
            return response()->json(['message' => 'assinatura inválida'], 403);
        }

        $event = $gateway->parseWebhook($request);

        if ($event !== null) {
            $action($event);
        }

        // Sempre 200 em evento válido/ignorado — evita reentrega desnecessária.
        return response()->json(['received' => true]);
    }
}
