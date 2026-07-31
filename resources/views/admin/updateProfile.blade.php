@extends('layout.app')

@section('title', 'Update Profile')

@section('content')
<h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Account Settings /</span> Profile</h4>

<div class="row">
    <div class="col-md-12">
        <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card mb-4">
                <h5 class="card-header">Profile Details</h5>
                <!-- Avatar Upload -->
                <div class="card-body">
                    <div class="d-flex align-items-start align-items-sm-center gap-4">
                        <img
                            src="{{ $user->profile_picture_url }}"
                            alt="{{ $user->name }}"
                            class="d-block rounded-circle"
                            id="uploadedAvatar"
                            style="width: 100px; height: 100px; object-fit: cover;"
                        >
                        <div class="button-wrapper">
                            <label for="profile_picture" class="btn btn-primary me-2 mb-2" tabindex="0">
                                <span class="d-none d-sm-block">Upload new photo</span>
                                <i class="bx bx-upload d-block d-sm-none"></i>
                                <input
                                    type="file"
                                    name="profile_picture"
                                    id="profile_picture"
                                    class="account-file-input"
                                    hidden
                                    accept="image/png,image/jpeg,image/webp"
                                >
                            </label>
                            <button type="button" class="btn btn-outline-secondary account-image-reset mb-2">
                                <i class="bx bx-reset d-block d-sm-none"></i>
                                <span class="d-none d-sm-block">Reset</span>
                            </button>

                            <p class="text-muted mb-0">Allowed JPG, PNG or WEBP. Max size of 2MB.</p>
                            @error('profile_picture')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <hr class="my-0">
                <div class="card-body">
                    <div class="row">
                        <!-- Name Field -->
                        <div class="mb-3 col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $user->name) }}"
                                autofocus
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email Field -->
                        <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
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
                        <div class="mb-3 col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <input
                                type="text"
                                id="role"
                                class="form-control"
                                value="{{ ucfirst($user->role) }}"
                                disabled
                            >
                            <small class="text-muted d-block mt-1">
                                <i class="bx bx-info-circle"></i> Your role cannot be changed. Contact the system administrator if you need assistance.
                            </small>
                        </div>

                        <!-- Member Since -->
                        <div class="mb-3 col-md-6">
                            <label class="form-label">Member Since</label>
                            <input
                                type="text"
                                class="form-control"
                                value="{{ $user->created_at->format('M d, Y') }}"
                                disabled
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h5 class="card-header">Change Password</h5>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Leave the password fields empty if you don't want to change it. Otherwise, enter your current password for confirmation.
                    </p>

                    <div class="row">
                        <!-- Current Password (Confirmation) -->
                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="current_password">Current Password <span class="text-danger">*</span></label>
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
                        </div>

                        <!-- New Password -->
                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="new_password">New Password</label>
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
                            <small class="text-muted d-block mt-1">Minimum 8 characters</small>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="new_password_confirmation">Confirm New Password</label>
                            <input
                                type="password"
                                name="new_password_confirmation"
                                id="new_password_confirmation"
                                class="form-control"
                                placeholder="Confirm new password"
                            >
                        </div>
                    </div>

                    <!-- Password Requirements -->
                    <ul class="list-unstyled mb-3">
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
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                            <i class="bx bx-arrow-back"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/pages-account-settings-account.js') }}"></script>
<script>
    // Validate password confirmation in real-time
    document.addEventListener('DOMContentLoaded', function() {
        const newPasswordField = document.getElementById('new_password');
        const confirmPasswordField = document.getElementById('new_password_confirmation');

        if (newPasswordField && confirmPasswordField) {
            confirmPasswordField.addEventListener('input', function() {
                if (this.value && newPasswordField.value !== this.value) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            newPasswordField.addEventListener('input', function() {
                if (confirmPasswordField.value && this.value !== confirmPasswordField.value) {
                    confirmPasswordField.classList.add('is-invalid');
                } else if (confirmPasswordField.value) {
                    confirmPasswordField.classList.remove('is-invalid');
                }
            });
        }
    });
</script>
@endsection
