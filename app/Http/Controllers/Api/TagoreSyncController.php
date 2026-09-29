<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tagore\OfflineSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TagoreSyncController extends Controller
{
    public function __construct(private readonly OfflineSyncService $sync)
    {
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
            'device_uuid' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $user = User::where(function ($q) use ($credentials) {
            $q->where('email', $credentials['login'])
                ->orWhere('mobile_no', $credentials['login']);
        })->first();

        if (!$user || !$user->password || !Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (in_array((int) $user->usergroup_id, [6, 7], true)) {
            return response()->json(['message' => 'Use the parent/student mobile authentication for this account.'], 403);
        }

        $device = $this->sync->registerDevice($user, $credentials);
        $token = $user->createToken('tagore-'.$credentials['platform'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'school_id' => $user->school_id, 'usergroup_id' => $user->usergroup_id],
            'device' => $device,
        ]);
    }

    public function bootstrap(Request $request)
    {
        $data = $request->validate(['device_uuid' => ['required', 'string', 'max:100']]);
        return response()->json($this->sync->bootstrap($request->user(), $data['device_uuid']));
    }

    public function push(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:100'],
            'changes' => ['required', 'array', 'max:500'],
        ]);

        return response()->json([
            'results' => $this->sync->push($request->user(), $data['device_uuid'], $data['changes']),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}