<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Show the user profile page
     */
    public function show()
    {
        $user = Auth::user();
        return view('pages.profile', compact('user'));
    }

    /**
     * Show the user profile edit form
     */
    public function edit()
    {
        $user = Auth::user();
        return view('pages.profileUpdate', compact('user'));
    }

    /**
     * Update the user's profile
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Validate the input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'required|current_password',
            'new_password' => 'nullable|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Current password is required for security confirmation.',
            'current_password.current_password' => 'The current password is incorrect.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password.confirmed' => 'Password confirmation does not match.',
            'email.unique' => 'This email address is already in use.',
        ]);

        try {
            // Update name and email
            $user->name = $validated['name'];
            $user->email = $validated['email'];

            // Update password if provided
            if (!empty($validated['new_password'])) {
                $user->password = Hash::make($validated['new_password']);
            }

            $user->save();

            return redirect()->route('profile.show')
                ->with('success', 'Profile updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update profile. Please try again.')
                ->withInput();
        }
    }
}
