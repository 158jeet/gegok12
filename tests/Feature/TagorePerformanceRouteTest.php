<?php

namespace Tests\Feature;

use App\Http\Controllers\Tagore\PerformanceController;
use App\Http\Controllers\Tagore\TaskController;
use Illuminate\Http\Request;
use Tests\TestCase;

class TagorePerformanceRouteTest extends TestCase
{
    public function test_system_health_and_staff_performance_use_distinct_routes(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertSame('tagore/system/performance', $routes->getByName('tagore.performance')->uri());
        $this->assertSame('tagore/performance', $routes->getByName('tagore.performance.index')->uri());

        $staffPerformance = $routes->match(Request::create('/tagore/performance', 'GET'));
        $this->assertSame(TaskController::class . '@performance', $staffPerformance->getActionName());

        $systemHealth = $routes->match(Request::create('/tagore/system/performance', 'GET'));
        $this->assertSame(PerformanceController::class . '@index', $systemHealth->getActionName());
    }
}
