<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class LegacyDeviceLogoutDisabledTest extends TestCase
{
    public function test_parent_legacy_device_logout_is_disabled(): void
    {
        $this->postJson('/api/logout/devices', ['mobile_no' => '9876543210'])
            ->assertStatus(410);
    }

    public function test_teacher_legacy_device_logout_is_disabled(): void
    {
        $this->postJson('/api/teacher/logout/devices', ['mobile_no' => '9876543210'])
            ->assertStatus(410);
    }
}
