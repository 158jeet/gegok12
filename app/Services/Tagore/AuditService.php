<?php

namespace App\Services\Tagore;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(Request $request, string $action, ?string $entityType = null, ?int $entityId = null, ?int $institutionId = null, ?array $old = null, ?array $new = null): void
    {
        DB::table('tagore_audit_events')->insert([
            'user_id' => optional($request->user())->id,
            'institution_id' => $institutionId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values_json' => $old ? json_encode($old) : null,
            'new_values_json' => $new ? json_encode($new) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
