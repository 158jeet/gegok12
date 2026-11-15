<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Events\SinglePushEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Mail\ChangePassword;
use App\Models\User;
use App\Services\Security\RecoveryChallengeService;
use App\Services\Security\RecoveryCodeDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UserController extends Controller
{
    public function updatetoken(Request $request)
    {
        try {
            $user = User::where('id', Auth::id())
                ->where('school_id', Auth::user()->school_id)
                ->firstOrFail();

            $user->platform_token = $request->device_name;
            $user->save();

            return ['message' => 'Token Updated Successfully'];
        } catch (Throwable $e) {
            Log::warning('API device token update failed.', ['exception' => get_class($e)]);
            return response()->json(['success' => false, 'message' => 'Unable to update token.'], 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = User::findOrFail(Auth::id());

            if (!Hash::check($request->oldpassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password Update Failed',
                ], 422);
            }

            $user->password = Hash::make($request->newpassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password Updated Successfully',
            ], 200);
        } catch (Throwable $e) {
            Log::warning('Authenticated API password change failed.', ['exception' => get_class($e)]);
            return response()->json(['success' => false, 'message' => 'Password Update Failed'], 500);
        }
    }

    /**
     * Start a mobile API password recovery. Unknown and ineligible accounts
     * receive the same response as eligible accounts to avoid enumeration.
     */
    public function resetPassword(Request $request, RecoveryChallengeService $challenges, RecoveryCodeDeliveryService $delivery)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|digits:10',
            'usergroup' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'If the account is eligible, a verification code will be sent.',
            ], 422);
        }

        try {
            $user = User::with('userprofile')
                ->where('mobile_no', $request->mobile_no)
                ->where('usergroup_id', $request->usergroup)
                ->first();

            if (
                $user &&
                $user->status === 'active' &&
                $user->userprofile &&
                $user->userprofile->status === 'active'
            ) {
                try {
                    $issued = $challenges->issue(
                        $user,
                        RecoveryChallengeService::API_PASSWORD_RESET,
                        $request->ip()
                    );

                    $delivery->send($issued['code'], $user->mobile_no);
                } catch (Throwable $e) {
                    // Keep the response indistinguishable for throttled or failed delivery.
                    Log::notice('API recovery challenge was not issued or delivered.', [
                        'exception' => get_class($e),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Check sms to reset the password',
            ], 200);
        } catch (Throwable $e) {
            Log::warning('API password recovery initiation failed.', ['exception' => get_class($e)]);
            return response()->json([
                'success' => true,
                'message' => 'Check sms to reset the password',
            ], 200);
        }
    }

    /**
     * Verify the first OTP step. The challenge remains unconsumed for the
     * final password-change request, but cannot be used after its expiry.
     */
    public function storePassword(Request $request, RecoveryChallengeService $challenges)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|digits:10',
            'usergroup' => 'required|integer',
            'password' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Password Does Not Match',
            ], 422);
        }

        try {
            $user = User::where('mobile_no', $request->mobile_no)
                ->where('usergroup_id', $request->usergroup)
                ->first();

            if (!$user || !$challenges->verify(
                $user,
                RecoveryChallengeService::API_PASSWORD_RESET,
                (string) $request->password
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password Does Not Match',
                ], 422);
            }

            $user->is_reset = 1;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password Reset Successfully',
            ], 200);
        } catch (Throwable $e) {
            Log::warning('API password recovery OTP verification failed.', ['exception' => get_class($e)]);
            return response()->json([
                'success' => false,
                'message' => 'Password Does Not Match',
            ], 422);
        }
    }

    public function checkReset(Request $request)
    {
        $user = User::where('mobile_no', $request->mobile_no)
            ->where('usergroup_id', $request->usergroup)
            ->first();

        return response()->json([
            'success' => true,
            'is_reset' => $user ? (int) $user->is_reset : 0,
        ], 200);
    }

    /**
     * Complete recovery only with the same still-valid, previously verified
     * challenge. Consumption, password update and token revocation are atomic.
     */
    public function resetChangePassword(Request $request, RecoveryChallengeService $challenges)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|digits:10',
            'usergroup' => 'required|integer',
            'oldpassword' => 'required|digits:6',
            'newpassword' => 'required|string|min:8',
            'confirmpassword' => 'required|same:newpassword',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Change Password Failed',
            ], 422);
        }

        try {
            $user = DB::transaction(function () use ($request, $challenges) {
                $user = User::where('mobile_no', $request->mobile_no)
                    ->where('usergroup_id', $request->usergroup)
                    ->lockForUpdate()
                    ->first();

                if (!$user || !$challenges->consumeVerified(
                    $user,
                    RecoveryChallengeService::API_PASSWORD_RESET,
                    (string) $request->oldpassword
                )) {
                    return null;
                }

                $user->password = Hash::make($request->newpassword);
                $user->platform_token = null;
                $user->device_id = null;
                $user->is_reset = 0;
                $user->save();

                // Revoke only after successful proof, inside the same transaction.
                $user->tokens()->delete();

                return $user;
            });

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Change Password Failed',
                ], 422);
            }

            try {
                if ($user->email) {
                    Mail::to($user->email)->queue(new ChangePassword($user));
                }

                event(new SinglePushEvent([
                    'school_id' => $user->school_id,
                    'user_id' => $user->id,
                    'message' => 'Changed Password Successfully',
                    'type' => 'private message',
                ]));
            } catch (Throwable $notificationError) {
                Log::warning('Password recovery notification dispatch failed.', [
                    'exception' => get_class($notificationError),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Changed Password Successfully',
            ], 200);
        } catch (Throwable $e) {
            Log::warning('API password recovery completion failed.', ['exception' => get_class($e)]);
            return response()->json([
                'success' => false,
                'message' => 'Change Password Failed',
            ], 500);
        }
    }
}
