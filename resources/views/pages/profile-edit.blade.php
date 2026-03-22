@extends('layout.user')

@section('title', 'Update Profile')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Update Your Profile</h4>
                    <p class="text-muted">Update your personal information and password</p>
                </div>
                <a href="{{ route('profile') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-arrow-back me-1"></i>Back to Profile
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xxl-4 col-md-6">
            <!-- Profile Card -->   
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <p class="mb-2">
                            <strong>{{ $user->name }}</strong>
                        </p>
                        <p class="text-muted small">{{ $user->email }}</p>
                        <span class="badge bg-label-{{ $user->role === 'admin' ? 'danger' : 'primary' }}">{{ ucfirst($user->role) }}</span>
                    </div>
                    <p class="small text-muted mb-0">Account created at {{ $user->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>

        <div class="col-xxl-8 col-md-6">
            <!-- Update Profile Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Name Field -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                id="name" 
                                class="form-control @error('name') is-invalid @enderror" 
                                value="{{ old('name', $user->name) }}"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email Field -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input 
                                type="email" 
                                name="email" 
                                id="email" 
                                class="form-control @error('email') is-invalid @enderror" 
                                value="{{ old('email', $user->email) }}"
                                required
                            >
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Role Display (Read-only) -->
                        <div class="mb-4">
                            <label for="role" class="form-label">Role</label>
                            <input 
                                type="text" 
                                id="role" 
                                class="form-control" 
                                value="{{ ucfirst($user->role) }}"
                                disabled
                            >
                            <small class="text-muted d-block mt-2">
                                <i class="bx bx-info-circle"></i> Your role cannot be changed. Contact the administrator if you need assistance.
                            </small>
                        </div>

                        <hr class="my-4">

                        <!-- Password Section Header -->
                        <h6 class="mb-3">Change Password</h6>
                        <p class="small text-muted mb-3">
                            Leave empty if you don't want to change your password. Otherwise, enter your current password for confirmation.
                        </p>

                        <!-- Current Password (Confirmation) -->
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                name="current_password" 
                                id="current_password" 
                                class="form-control @error('current_password') is-invalid @enderror" 
                                placeholder="Enter your current password"
                                required
                            >
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2">
                                <i class="bx bx-lock"></i> Required for security verification
                            </small>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input 
                                type="password" 
                                name="new_password" 
                                id="new_password" 
                                class="form-control @error('new_password') is-invalid @enderror" 
                                placeholder="Enter new password (optional)"
                                minlength="8"
                            >
                            @error('new_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2">Minimum 8 characters</small>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-4">
                            <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                            <input 
                                type="password" 
                                name="new_password_confirmation" 
                                id="new_password_confirmation" 
                                class="form-control" 
                                placeholder="Confirm new password"
                            >
                            <small class="text-muted d-block mt-2">
                                <i class="bx bx-check-circle"></i> Must match the new password above
                            </small>
                        </div>

                        <!-- Alert Box -->
                        <div class="alert alert-warning mb-4" role="alert">
                            <h6 class="alert-heading mb-2">
                                <i class="bx bx-error-circle"></i> Important Security Notice
                            </h6>
                            <p class="mb-0">
                                Your current password is required to confirm any changes. This helps protect your account from unauthorized modifications.
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Save Changes
                            </button>
                            <a href="{{ route('profile') }}" class="btn btn-outline-secondary">
                                <i class="bx bx-arrow-back"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Password Requirements</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bx bxs-check-circle text-success"></i> At least 8 characters long
                        </li>
                        <li class="mb-2">
                            <i class="bx bxs-check-circle text-success"></i> Can include uppercase and lowercase letters
                        </li>
                        <li class="mb-2">
                            <i class="bx bxs-check-circle text-success"></i> Can include numbers and special characters
                        </li>
                        <li>
                            <i class="bx bxs-check-circle text-success"></i> Password confirmation must match exactly
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Show/hide password functionality
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                input.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        });
    });
</script>
@endsection
