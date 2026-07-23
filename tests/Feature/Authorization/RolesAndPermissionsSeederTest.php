<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authorization\Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_seven_initial_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $roles = Role::query()->pluck('name')->all();

        $this->assertEqualsCanonicalizing([
            'super_admin',
            'admin_clinica',
            'psicologo',
            'secretaria',
            'financeiro',
            'paciente',
            'responsavel_legal',
        ], $roles);
    }

    public function test_super_admin_receives_every_seeded_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = Role::findByName('super_admin', 'web');

        $this->assertEqualsCanonicalizing([
            'manage-users',
            'manage-clinic-settings',
            'view-audit-log',
            'platform.manage-tenants',
            'manage-financial',
        ], $superAdmin->permissions->pluck('name')->all());
    }

    public function test_admin_clinica_receives_only_clinic_scoped_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminClinica = Role::findByName('admin_clinica', 'web');

        $this->assertEqualsCanonicalizing([
            'manage-users',
            'manage-clinic-settings',
            'view-audit-log',
            'manage-financial',
        ], $adminClinica->permissions->pluck('name')->all());
    }

    public function test_financeiro_receives_manage_financial_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $financeiro = Role::findByName('financeiro', 'web');

        $this->assertEqualsCanonicalizing([
            'manage-financial',
        ], $financeiro->permissions->pluck('name')->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(7, Role::query()->count());
    }
}
