<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\RecoveryChallengeService;
use App\Services\Security\RecoveryCodeDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DeviceRecoveryController extends Controller
{
    private const PARENT_GROUP = User::PARENT_USERGROUP_ID;
    private const TEACHER_GROUP = User::TEACHER_USERGROUP_ID;

    public function requestParent(Request $request, RecoveryChallengeService $challenges, RecoveryCodeDeliveryService $delivery)
    {
        return $this->requestCode($request, self::PARENT_GROUP, RecoveryChallengeService::PARENT_DEVICE_RECOVERY, $challenges, $delivery);
    }

    public function requestTeacher(Request $request, RecoveryChallengeService $challenges, RecoveryCodeDeliveryService $delivery)
    {
        return $this->requestCode($request, self::TEACHER_GROUP, RecoveryChallengeService::TEACHER_DEVICE_RECOVERY, $challenges, $delivery);
    }

    public function confirmParent(Request $request, RecoveryChallengeService $challenges)
    {
        return $this->confirmCode($request, self::PARENT_GROUP, RecoveryChallengeService::PARENT_DEVICE_RECOVERY, $challenges);
    }

    public function confirmTeacher(Request $request, RecoveryChallengeService $challenges)
    {
        return $this->confirmCode($request, self::TEACHER_GROUP, RecoveryChallengeService::TEACHER_DEVICE_RECOVERY, $challenges);
    }

    private function requestCode(Request $request, int $groupId, string $purpose, RecoveryChallengeService $challenges, RecoveryCodeDeliveryService $delivery)
    {
        $mobile = $request->input('mobile_no', $request->input('email'));
        $validator = Validator::make(['mobile_no' => $mobile], ['mobile_no' => 'required|digits:10']);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'If the account is eligible, a verification code will be sent.'], 422);
        }

        try {
            $user = User::where('mobile_no', $mobile)
                ->where('usergroup_id', $groupId)
                ->where('status', 'active')
                ->first();

            if ($user) {
                try {
                    $issued = $challenges->issue($user, $purpose, $request->ip());
                    $delivery->send($issued['code'], $user->mobile_no);
                } catch (Throwable $e) {
                    Log::notice('Device recovery challenge was not issued or delivered.', ['exception' => get_class($e)]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Device recovery initiation failed.', ['exception' => get_class($e)]);
        }

        return response()->json([
            'success' => true,
            'message' => 'If the account is eligible, a verification code will be sent.',
        ], 200);
    }

    private function confirmCode(Request $request, int $groupId, string $purpose, RecoveryChallengeService $challenges)
    {
        $mobile = $request->input('mobile_no', $request->input('email'));
        $code = $request->input('code', $request->input('otp'));
        $validator = Validator::make([
            'mobile_no' => $mobile,
            'code' => $code,
        ], [
            'mobile_no' => 'required|digits:10',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Device recovery verification failed.'], 422);
        }

        try {
            $revoked = DB::transaction(function () use ($mobile, $code, $groupId, $purpose, $request, $challenges) {
                $user = User::where('mobile_no', $mobile)
                    ->where('usergroup_id', $groupId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$user || !$challenges->verify($user, $purpose, (string) $code, $request->ip())) {
                    return false;
                }

                if (!$challenges->consumeVerified($user, $purpose, (string) $code)) {
                    return false;
                }

                $user->tokens()->delete();
                $user->platform_token = null;
                $user->device_id = null;
                $user->save();

                return true;
            });

            if (!$revoked) {
                return response()->json(['success' => false, 'message' => 'Device recovery verification failed.'], 422);
            }

            return response()->json(['success' => true, 'message' => 'Logged out from all devices.'], 200);
        } catch (Throwable $e) {
            Log::warning('Device recovery confirmation failed.', ['exception' => get_class($e)]);
            return response()->json(['success' => false, 'message' => 'Device recovery verification failed.'], 422);
        }
    }
}
