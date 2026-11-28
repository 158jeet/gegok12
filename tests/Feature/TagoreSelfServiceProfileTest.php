<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class TagoreSelfServiceProfileTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); DB::beginTransaction(); }
    protected function tearDown(): void { if (DB::transactionLevel() > 0) DB::rollBack(); parent::tearDown(); }
    public function test_tagore_roles_can_open_their_own_profile_page(): void
    {
        foreach (['owner@tagore-demo.local','parent@tagore-demo.local','cashier@tagore-demo.local','student@tagore-demo.local'] as $email) {
            $this->actingAs(User::where('email',$email)->firstOrFail())->get(route('tagore.profile.edit'))->assertOk()->assertSee('My profile')->assertSee('Save profile');
        }
    }
    public function test_user_can_update_contact_details_and_email_change_requires_reverification(): void
    {
        $owner=User::where('email','owner@tagore-demo.local')->firstOrFail(); $email='owner-profile-'.bin2hex(random_bytes(4)).'@tagore-demo.local';
        $this->actingAs($owner)->patch(route('tagore.profile.update'),['name'=>'Updated Tagore Owner','email'=>$email,'mobile_no'=>'9876543210'])->assertRedirect()->assertSessionHas('success');
        $owner->refresh(); $this->assertSame('Updated Tagore Owner',$owner->name); $this->assertSame($email,$owner->email); $this->assertSame('9876543210',$owner->mobile_no); $this->assertFalse((bool)$owner->email_verified); $this->assertNull($owner->email_verified_at);
    }
}
