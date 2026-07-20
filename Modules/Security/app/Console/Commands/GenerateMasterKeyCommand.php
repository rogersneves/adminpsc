<?php

declare(strict_types=1);

namespace Modules\Security\Console\Commands;

use Illuminate\Console\Command;

class GenerateMasterKeyCommand extends Command
{
    protected $signature = 'security:master-key:generate';

    protected $description = 'Gera uma nova Master Key (32 bytes, base64) para ENCRYPTION_MASTER_KEY no .env';

    public function handle(): int
    {
        $key = base64_encode(random_bytes(32));

        $this->warn('Cole a linha abaixo no seu .env (NUNCA versione este valor):');
        $this->newLine();
        $this->line("ENCRYPTION_MASTER_KEY={$key}");
        $this->newLine();
        $this->warn('Trocar a Master Key torna ilegível qualquer dado já cifrado com a anterior.');

        return self::SUCCESS;
    }
}
