<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tagore\SyncService;
use Illuminate\Http\Request;

class TagoreSyncController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    public function bootstrap(Request $request)
    {
        return response()->json($this->sync->bootstrap((int) $request->user()->id));
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'device_id' => 'required|string|max:120',
            'platform' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:40',
        ]);

        $this->sync->registerDevice((int) $request->user()->id, $data);

        return response()->json(['ok' => true]);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'events' => 'required|array|max:100',
            'events.*.event_uuid' => 'nullable|uuid',
            'events.*.device_id' => 'required|string|max:120',
            'events.*.entity_type' => 'required|string|max:80',
            'events.*.operation' => 'required|string|max:30',
            'events.*.payload' => 'nullable|array',
            'events.*.occurred_at' => 'nullable|date',
        ]);

        return response()->json($this->sync->process((int) $request->user()->id, $data['events']));
    }
}
