@extends('layout.app')

@section('title', 'Categories')

@section('content')
    <div class="mt-3">
        @if(Auth::user()->role === 'admin')
        <!-- Button trigger modal -->
        <div class="text-end">
            <button
                type="button"
                class="btn btn-outline-primary me-1"
                data-bs-toggle="modal"
                data-bs-target="#encoderModal"
            >
                Add Encoder
            </button>
            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#userModal"
            >
                Add User
            </button>
        </div>
        @endif

        <!-- Add Encoder Modal -->
        <div class="modal fade" id="encoderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('users.encoders.store') }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Encoder</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                An Encoder is a name that can be picked under <strong>Prepared By</strong> on every document.
                                It cannot sign in, so several people can use one login and still put their own name on what they encode.
                            </p>
                            <label for="encoder_name" class="form-label">Name</label>
                            <input type="text" name="name" id="encoder_name" class="form-control" placeholder="e.g., Maria Santos" maxlength="255" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add User Modal -->
        <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('users.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add User</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        Name
                                    </label>
                                    <input
                                        type="text"
                                        name="name"
                                        id="name"
                                        class="form-control"
                                        placeholder="e.g., Juan Dela Cruz"
                                        required
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        class="form-control"
                                        placeholder="e.g., juan@saims.com"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">
                                        Role
                                    </label>
                                    <select
                                        name="role"
                                        id="role"
                                        class="form-control"
                                        required
                                    >
                                        <option value="pos">POS (Cashier)</option>
                                        <option value="user">User (Limited Staff View)</option>
                                        <option value="admin">Admin</option>
                                        <option value="admin_staff">Admin Staff</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        Password
                                    </label>
                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="form-control"
                                        placeholder="Minimum 6 characters"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <ul class="nav nav-tabs mt-4 mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'users' ? 'active' : '' }}" href="{{ route('users.index') }}">Users</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'encoders' ? 'active' : '' }}" href="{{ route('users.index', ['tab' => 'encoders']) }}">Encoders</a>
        </li>
    </ul>

    @if($tab === 'users')
    <div class="card">
        <h5 class="card-header">Users</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Avatar</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $isAdmin = $user->role === 'admin';
                            $isSelf = $user->id === auth()->id();
                        @endphp
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <img src="{{ $user->profile_picture_url }}" alt="{{ $user->name }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                            </td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $user->is_active ? 'Active' : 'Suspended' }}
                                </span>
                            </td>
                            <td>
                                @if(Auth::user()->role === 'admin')
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <form
                                            action="{{ route('users.toggle-status', $user) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="dropdown-item"
                                                @if ($isAdmin || $isSelf)
                                                    disabled
                                                    title="{{ $isAdmin ? 'Admin accounts cannot be suspended' : 'You cannot suspend your own account' }}"
                                                @else
                                                    onclick="return confirm('{{ $user->is_active ? 'Suspend' : 'Reactivate' }} this user?')"
                                                @endif
                                            >
                                                <i class="bx {{ $user->is_active ? 'bx-lock-alt' : 'bx-lock-open-alt' }} me-1"></i>
                                                {{ $user->is_active ? 'Suspend' : 'Reactivate' }}
                                            </button>
                                        </form>

                                        <div class="dropdown-divider"></div>

                                        <form
                                            action="{{ route('users.destroy', $user) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="dropdown-item text-danger"
                                                @if ($isAdmin)
                                                    disabled
                                                    title="Admin accounts cannot be deleted"
                                                @else
                                                    onclick="return confirm('Are you sure you want to delete this user?')"
                                                @endif
                                            >
                                                <i class="bx bx-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No users yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @else
    <div class="card">
        <h5 class="card-header">Encoders <small class="text-muted fw-normal">— names for Prepared By (no login)</small></h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($encoders as $encoder)
                        <tr>
                            <td>{{ $encoder->name }}</td>
                            <td>
                                <span class="badge {{ $encoder->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $encoder->is_active ? 'On the list' : 'Removed' }}
                                </span>
                            </td>
                            <td>
                                @if(Auth::user()->role === 'admin')
                                    <form action="{{ route('users.encoders.toggle', $encoder) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ $encoder->is_active ? 'Remove from list' : 'Put back' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">No Encoders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

@section('scripts')
@endsection