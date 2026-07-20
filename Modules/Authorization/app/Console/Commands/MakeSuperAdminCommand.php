<?php

declare(strict_types=1);

namespace Modules\Authorization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Models\User;

/**
 * Não existe formulário público para criar um Super Admin — é de propósito
 * (uso exclusivo da plataforma SaaS, ver docs/01-Arquitetura.md).
 */
class MakeSuperAdminCommand extends Command
{
    protected $signature = 'authorization:make-super-admin {email} {name} {--password=}';

    protected $description = 'Cria (ou promove) um usuário Super Admin, sem tenant, para uso exclusivo da plataforma';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password') ?: bin2hex(random_bytes(12));

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->tenant_id = null;
        $user->password = Hash::make($password);
        $user->email_verified_at ??= now();
        $user->save();

        $user->syncRoles(['super_admin']);

        $this->info("Super Admin pronto: {$email}");

        if (! $this->option('password')) {
            $this->warn("Senha gerada (guarde agora, não será exibida de novo): {$password}");
        }

        return self::SUCCESS;
    }
}
