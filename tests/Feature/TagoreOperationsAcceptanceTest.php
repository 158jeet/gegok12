<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreOperationsAcceptanceTest extends TestCase
{

    public function test_inventory_form_defaults_blank_quantity_counters_to_zero(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->value('id');
        $this->assertNotNull($institutionId);

        $sku = 'QA-' . bin2hex(random_bytes(6));
        $itemName = 'QA inventory defaults ' . bin2hex(random_bytes(4));

        $response = $this->actingAs($owner)->from(route('tagore.operations.index', ['module' => 'inventory']))
            ->post(route('tagore.operations.store', 'inventory'), [
                'institution_id' => $institutionId,
                'item_name' => $itemName,
                'category' => 'Acceptance QA',
                'sku' => $sku,
                'status' => 'active',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tagore_inventory_records', [
            'institution_id' => $institutionId,
            'item_name' => $itemName,
            'sku' => $sku,
            'quantity' => 0,
            'reorder_level' => 0,
        ]);
    }

    public function test_operations_reject_negative_financial_amounts_before_persisting(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->value('id');
        $this->assertNotNull($institutionId);

        $description = 'QA negative expense ' . bin2hex(random_bytes(5));
        $response = $this->actingAs($owner)->post(route('tagore.operations.store', 'expense'), [
            'institution_id' => $institutionId,
            'expense_date' => now()->toDateString(),
            'category' => 'Acceptance test',
            'description' => $description,
            'amount' => -1,
            'vendor' => 'Synthetic QA',
            'status' => 'pending',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('tagore_expense_records', [
            'institution_id' => $institutionId,
            'description' => $description,
        ]);
    }

    public function test_operations_reject_unknown_status_and_invalid_json(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->value('id');
        $this->assertNotNull($institutionId);

        $invalidStatusDescription = 'QA invalid status ' . bin2hex(random_bytes(5));
        $this->actingAs($owner)->post(route('tagore.operations.store', 'expense'), [
            'institution_id' => $institutionId,
            'expense_date' => now()->toDateString(),
            'category' => 'Acceptance test',
            'description' => $invalidStatusDescription,
            'amount' => 10,
            'status' => 'posted-to-production',
        ])->assertSessionHasErrors('status');

        $invalidJsonTitle = 'QA invalid JSON ' . bin2hex(random_bytes(5));
        $this->actingAs($owner)->post(route('tagore.operations.store', 'survey'), [
            'institution_id' => $institutionId,
            'title' => $invalidJsonTitle,
            'audience' => 'staff',
            'is_anonymous' => '0',
            'questions_json' => '{not valid json',
            'status' => 'draft',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_expense_records', ['description' => $invalidStatusDescription]);
        $this->assertDatabaseMissing('tagore_survey_records', ['title' => $invalidJsonTitle]);
    }
}
