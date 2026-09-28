<?php

namespace Tests\\Feature;

use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class TagoreOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertTrue($routes->contains('api/v2/tagore/sync/login'));
        $this->assertTrue($routes->contains('api/v2/tagore/sync/bootstrap'));
        $this->assertTrue($routes->contains('api/v2/tagore/sync/push'));
    }
}
