<?php

declare(strict_types=1);

namespace Modules\Tenant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Tenant\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'plan' => 'trial',
            'status' => 'active',
            'settings' => [],
        ];
    }
}
