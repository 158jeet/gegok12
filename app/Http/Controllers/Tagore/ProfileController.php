<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('tagore.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile_no' => ['nullable', 'string', 'max:30'],
        ]);

        $emailChanged = strcasecmp((string) $user->email, (string) $validated['email']) !== 0;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->mobile_no = $validated['mobile_no'] ?? null;
        if ($emailChanged) {
            $user->email_verified = false;
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $user = $request->user();
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.'])->withInput();
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return back()->with('password_success', 'Password changed successfully.');
    }
}
