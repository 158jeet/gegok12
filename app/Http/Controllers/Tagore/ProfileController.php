<?php

namespace App\\Http\\Controllers\\Tagore;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('tagore.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:25'],
        ]);

        $request->user()->forceFill($validated)->save();

        return redirect()->route('tagore.profile')->with('profile_status', 'Your profile details have been updated.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();

        return redirect()->route('tagore.profile')->with('password_status', 'Your password has been changed.');
    }
}
