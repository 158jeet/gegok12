<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class LegacyMutationHttpMethodTest extends TestCase
{
    public function test_holiday_delete_is_registered_as_delete_only(): void
    {
        $routes = app('router')->getRoutes();

        $deleteRoute = $routes->match(Request::create('/admin/holiday/delete/123', 'DELETE'));

        $this->assertSame('DELETE', $deleteRoute->methods()[0]);
        $this->assertStringEndsWith('HolidaysController@destroy', $deleteRoute->getActionName());
    }

    public function test_holiday_delete_cannot_be_triggered_by_get_or_head(): void
    {
        $routes = app('router')->getRoutes();

        foreach (['GET', 'HEAD'] as $method) {
            try {
                $routes->match(Request::create('/admin/holiday/delete/123', $method));
                $this->fail($method . ' must not match the holiday delete endpoint.');
            } catch (MethodNotAllowedHttpException $exception) {
                $this->assertNotEmpty($exception->getHeaders()['Allow'] ?? '');
            }
        }
    }
}
