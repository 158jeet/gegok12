<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TagoreProfileSelfServiceTest extends TestCase
{
    public function test_every_demo_role_can_open_its_own_profile_and_sign_out(): void
    {
        $emails = [
            'owner@tagore-demo.local',
            'principal@tagore-demo.local',
            'coordinator@tagore-demo.local',
            'teacher@tagore-demo.local',
            'accounts@tagore-demo.local',
            'fees@tagore-demo.local',
            'parent@tagore-demo.local',
            'student@tagore-demo.local',
        ];

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->whereNull('deleted_at')->firstOrFail();

            $this->actingAs($user)
                ->get(route('tagore.profile'))
                ->assertOk()
                ->assertSee('My profile & settings', false)
                ->assertSee('Change password');

            $this->post(route('logout'))->assertRedirect();
            $this->assertGuest();
        }
    }

    public function test_user_can_update_their_own_profile_details(): void
    {
        $user = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();

        $this->actingAs($user)->patch(route('tagore.profile.update'), [
            'name' => 'Demo Owner Updated',
            'mobile_no' => '9876543210',
        ])->assertRedirect(route('tagore.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Demo Owner Updated',
            'mobile_no' => '9876543210',
        ]);
    }
}
