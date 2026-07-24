<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base de toda Notification transacional do projeto. `via()` lê os canais de
 * `config('notifications.channels.default')` em vez de cada subclasse decidir
 * o próprio canal — é o que torna a arquitetura pluggable (Fase 7): adicionar
 * SMS/WhatsApp no futuro é acrescentar o nome do canal aqui e implementar o
 * método `to{Canal}()` correspondente em cada subclasse, sem tocar nesta base
 * nem nas subclasses já existentes.
 *
 * `SerializesModels` garante que, ao ser enfileirada, a notification serializa
 * qualquer Model em suas propriedades como referência (classe + chave primária),
 * não como snapshot de atributos — payload de fila nunca carrega o valor
 * decifrado de um campo `EnvelopeEncrypted` (docs/04-Seguranca.md).
 */
abstract class TenantNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function via(object $notifiable): array
    {
        return config('notifications.channels.default', ['mail', 'database']);
    }
}
