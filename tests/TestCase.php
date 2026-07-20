<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Papéis/permissões são pré-requisito de quase todo fluxo autenticado
     * (docs/06-Roadmap.md Fase 1); reseeda-los em cada teste evita duplicar
     * `$this->seed(RolesAndPermissionsSeeder::class)` em toda classe de teste.
     */
    protected $seed = true;
}
