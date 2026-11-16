<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyMutationHttpMethodTest extends TestCase
{
    public function test_academic_year_delete_is_registered_as_delete_only(): void
    {
        $matchingRoutes = collect(app('router')->getRoutes())->filter(
            fn ($route) => $route->uri() === 'admin/academic/delete/{id}'
                && str_ends_with($route->getActionName(), 'AcademicYearController@destroy')
        );

        $this->assertCount(1, $matchingRoutes);
        $this->assertSame(['DELETE'], $matchingRoutes->first()->methods());
    }
}
