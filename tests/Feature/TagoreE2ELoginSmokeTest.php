<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreE2ELoginSmokeTest extends TestCase
{
    public function test_login_page_renders_in_testing_environment(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }
}
