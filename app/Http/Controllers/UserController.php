<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'User Management is restricted to full admin accounts.');
            return redirect()->route('admin.dashboard');
        }

        $users = User::loginAccounts()->orderBy('name')->paginate(15);
        $encoders = User::where('is_encoder', true)->orderBy('name')->get();

        return view('admin.users', compact('users', 'encoders'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Managing user accounts is restricted to full admin accounts.');
            return redirect()->route('users.index');
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:user,pos,admin,admin_staff',
            'password' => 'required|min:6',
        ]);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => bcrypt($request->password),
        ]);

        // Never log the plaintext password — description/metadata deliberately
        // omit it entirely (not even in metadata), per the refactor's
        // constraint against logging sensitive data.
        ActivityLog::record(
            module: 'User',
            action: 'created',
            loggable: $user,
            description: "Created user account {$user->name} ({$user->email}) with role {$user->role}",
        );

        Alert::success('Success', 'User created successfully');
        return redirect()->route('users.index');
    }

    /**
     * Add a name that can be picked under "Prepared By" but can never sign in. The row gets a random
     * password and an address that does not exist, so it cannot be used for login or password reset.
     */
    public function storeEncoder(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Managing Encoders is restricted to full admin accounts.');
            return redirect()->route('users.index');
        }

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $name = trim($validated['name']);

        if (User::where('is_encoder', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            Alert::error('Already listed', "An Encoder named {$name} already exists.");
            return redirect()->route('users.index');
        }

        $encoder = User::create([
            'name' => $name,
            'email' => 'encoder-' . Str::uuid() . '@encoder.invalid',
            'password' => Str::random(64),
            'role' => 'user',
            'is_active' => true,
            'is_encoder' => true,
        ]);

        ActivityLog::record(
            module: 'User',
            action: 'created',
            loggable: $encoder,
            description: "Added Encoder {$encoder->name} (Prepared By name, no login)",
        );

        Alert::success('Success', "Encoder {$encoder->name} added.");
        return redirect()->route('users.index');
    }

    /**
     * Take an Encoder off the Prepared By list, or put them back. Never deleted: documents they were
     * picked on keep showing their name.
     */
    public function toggleEncoder(User $user)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Managing Encoders is restricted to full admin accounts.');
            return redirect()->route('users.index');
        }

        abort_unless($user->is_encoder, 404);

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record(
            module: 'User',
            action: $user->is_active ? 'reactivated' : 'removed',
            loggable: $user,
            description: ($user->is_active ? 'Put back on' : 'Removed from') . " the Prepared By list: Encoder {$user->name}",
        );

        Alert::success('Success', $user->is_active ? 'Encoder is back on the list.' : 'Encoder removed from the list. Past documents keep their name.');
        return redirect()->route('users.index');
    }

    public function destroy(string $id)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Managing user accounts is restricted to full admin accounts.');
            return redirect()->route('users.index');
        }

        $user = User::findOrFail($id);

        if ($user->is_encoder) {
            Alert::error('Cannot delete', 'Encoders are removed from the list, not deleted, so past documents keep their name.');
            return redirect()->route('users.index');
        }

        if ($user->role === 'admin') {
            Alert::error('Cannot delete', 'Admin accounts cannot be deleted.');
            return redirect()->route('users.index');
        }

        $userLabel = "{$user->name} ({$user->email})";
        $user->delete();

        ActivityLog::record(
            module: 'User',
            action: 'deleted',
            loggable: $user,
            description: "Deleted user account {$userLabel}",
        );

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
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Managing user accounts is restricted to full admin accounts.');
            return redirect()->route('users.index');
        }

        if ($user->is_encoder) {
            return redirect()->route('users.index');
        }

        if ($user->role === 'admin') {
            Alert::error('Not allowed', 'Admin accounts cannot be suspended.');
            return redirect()->route('users.index');
        }

        if ($user->id === auth()->id()) {
            Alert::error('Not allowed', 'You cannot suspend your own account.');
            return redirect()->route('users.index');
        }

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record(
            module: 'User',
            action: $user->is_active ? 'reactivated' : 'suspended',
            loggable: $user,
            description: ($user->is_active ? 'Reactivated' : 'Suspended') . " user account {$user->name}",
        );

        Alert::success('Success', $user->is_active ? 'User reactivated successfully' : 'User suspended successfully');
        return redirect()->route('users.index');
    }
}