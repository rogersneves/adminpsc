<?php

declare(strict_types=1);

namespace Modules\Security\Console\Commands;

use Illuminate\Console\Command;
use Modules\Security\Jobs\RotateEncryptionKeyJob;
use Modules\Security\Services\EncryptionService;

/**
 * Rotaciona a DEK de um contexto (ou de todos os contextos recifráveis registrados
 * em config('security.encryption_contexts')) e agenda a recifragem do dado antigo.
 *
 * Fluxo: rotate() aposenta a DEK ativa e cria a próxima versão ativa; a partir daí
 * toda nova cifragem já usa a nova versão. O RotateEncryptionKeyJob migra o dado
 * existente em background (fila) — ou inline com --sync (útil em instalação pequena
 * ou verificação manual). Dado ainda não migrado continua legível pela DEK aposentada.
 *
 * @see docs/04-Seguranca.md
 */
class RotateEncryptionKeyCommand extends Command
{
    protected $signature = 'security:rotate-key
        {context? : Contexto a rotacionar (omitido = todos os contextos registrados)}
        {--sync : Recifra inline em vez de despachar para a fila}';

    protected $description = 'Rotaciona a DEK de um contexto e recifra o dado antigo para a nova versão.';

    public function handle(EncryptionService $encryption): int
    {
        $contexts = $this->resolveContexts();

        if ($contexts === []) {
            $this->error('Nenhum contexto de criptografia registrado em config/security.php.');

            return self::FAILURE;
        }

        foreach ($contexts as $context) {
            $key = $encryption->rotate($context);
            $this->info("Contexto [{$context}] rotacionado para a versão {$key->version}.");

            if ($this->option('sync')) {
                (new RotateEncryptionKeyJob($context))->handle();
                $this->line("  Dado recifrado inline para a versão {$key->version}.");
            } else {
                RotateEncryptionKeyJob::dispatch($context);
                $this->line('  Recifragem despachada para a fila.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveContexts(): array
    {
        $registered = array_keys(config('security.encryption_contexts', []));

        $requested = $this->argument('context');

        if ($requested === null) {
            return $registered;
        }

        if (! in_array($requested, $registered, true)) {
            $this->error("Contexto [{$requested}] não está registrado como recifrável.");

            return [];
        }

        return [$requested];
    }
}
