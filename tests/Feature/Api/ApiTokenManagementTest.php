<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Gestão de tokens de API na área web autenticada (marco: API pública REST).
 */
class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_token_and_sees_it_once(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('paciente');

        $this->actingAs($user)->post('/api-tokens', ['name' => 'App do celular'])
            ->assertRedirect()
            ->assertSessionHas('newToken');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'App do celular',
        ]);
    }

    public function test_user_can_revoke_a_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('x')->accessToken;

        $this->actingAs($user)->delete("/api-tokens/{$token->id}")->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_a_user_cannot_revoke_another_users_token(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $owner->createToken('x')->accessToken;

        $intruder = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($intruder)->delete("/api-tokens/{$token->id}")->assertRedirect();

        // O token do dono continua existindo (o intruso só apaga os próprios).
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }
}
