<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        return view('admin.users', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'role' => 'required',
            'password' => 'required|min:6',
        ]);
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => bcrypt($request->password),
        ]);
        Alert::success('Success', 'User created successfully');
        return redirect()->route('users.index');
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            Alert::error('Cannot delete', 'Admin accounts cannot be deleted.');
            return redirect()->route('users.index');
        }

        $user->delete();
        Alert::success('Success', 'User deleted successfully');
        return redirect()->route('users.index');
    }

    /**
     * Suspend or reactivate a user account. Admin accounts and your own
     * account can't be suspended, for the same reason admin accounts can't
     * be deleted — the system must never be left with no usable admin.
     */
    public function toggleStatus(User $user)
    {
        if ($user->role === 'admin') {
            Alert::error('Not allowed', 'Admin accounts cannot be suspended.');
            return redirect()->route('users.index');
        }

        if ($user->id === auth()->id()) {
            Alert::error('Not allowed', 'You cannot suspend your own account.');
            return redirect()->route('users.index');
        }

        $user->update(['is_active' => ! $user->is_active]);

        Alert::success('Success', $user->is_active ? 'User reactivated successfully' : 'User suspended successfully');
        return redirect()->route('users.index');
    }
}