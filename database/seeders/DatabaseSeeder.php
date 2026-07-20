<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authorization\Database\Seeders\RolesAndPermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
