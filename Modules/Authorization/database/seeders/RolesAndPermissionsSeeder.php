<?php

declare(strict_types=1);

namespace Modules\Authorization\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 7 papéis iniciais (docs/01-Arquitetura.md). Permissões nascem só com o que já
 * existe no sistema hoje — cada módulo futuro registra as suas próprias permissões
 * quando for implementado, em vez de inventar permissões para telas que não existem.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    private const ROLES = [
        'super_admin',
        'admin_clinica',
        'psicologo',
        'secretaria',
        'financeiro',
        'paciente',
        'responsavel_legal',
    ];

    private const PERMISSIONS = [
        'manage-users',
        'manage-clinic-settings',
        'view-audit-log',
        'platform.manage-tenants',
        'manage-financial',
    ];

    public function run(): void
    {
        // Best prática do próprio pacote: garante que uma execução anterior (ou o
        // cache de 24h da spatie/laravel-permission) não deixe o registrar com uma
        // coleção de permissões desatualizada nesta execução.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('super_admin', 'web')->syncPermissions(self::PERMISSIONS);

        Role::findByName('admin_clinica', 'web')->syncPermissions([
            'manage-users',
            'manage-clinic-settings',
            'view-audit-log',
            'manage-financial',
        ]);

        // Primeira permissão real do papel `financeiro` (seedado desde a Fase 1, sem
        // uso até a Fase 5).
        Role::findByName('financeiro', 'web')->syncPermissions([
            'manage-financial',
        ]);
    }
}
