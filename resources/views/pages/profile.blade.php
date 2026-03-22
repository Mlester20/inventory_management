@extends('layout.user')

@section('title', 'My Profile')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">My Profile</h4>
                    <p class="text-muted">View and manage your account details</p>
                </div>
                <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                    <i class="bx bx-edit me-1"></i>Edit Profile
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bx bx-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bx bx-error-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Profile Card -->
        <div class="col-xxl-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <h5 class="mb-1">{{ $user->name }}</h5>
                        <p class="text-muted small mb-2">{{ $user->email }}</p>
                        <span class="badge bg-label-success">{{ ucfirst($user->role) }}</span>
                    </div>
                    <hr>
                    <div class="text-start">
                        <p class="mb-2">
                            <small class="text-muted">Member Since:</small><br>
                            <small class="fw-medium">{{ $user->created_at->format('M d, Y') }}</small>
                        </p>
                        <p class="mb-0">
                            <small class="text-muted">Last Updated:</small><br>
                            <small class="fw-medium">{{ $user->updated_at->format('M d, Y H:i A') }}</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Information -->
        <div class="col-xxl-8 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Full Name</label>
                            <p class="mb-0">{{ $user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email Address</label>
                            <p class="mb-0">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Role</label>
                            <p class="mb-0">
                                <span class="badge bg-label-{{ $user->role === 'admin' ? 'danger' : 'primary' }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Account Status</label>
                            <p class="mb-0">
                                <span class="badge bg-label-success">Active</span>
                            </p>
                        </div>
                    </div>

                    <hr>
                    
                    <div>
                        <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-sm">
                            <i class="bx bx-edit me-1"></i>Edit Information
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="bx bx-shield me-1"></i>Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">To change your password, please use the Edit Profile page. This ensures your account security.</p>
                <a href="{{ route('profile.edit') }}" class="btn btn-primary w-100">Go to Edit Profile</a>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            // Load purchases to get stats
            const purchasesRes = await axios.get('/api/purchases/history');
            const purchases = purchasesRes.data.data || purchasesRes.data || [];
            
            const totalSpending = purchases.reduce((sum, p) => sum + parseFloat(p.total_price), 0);
            document.getElementById('totalPurchases').textContent = purchases.length;
            document.getElementById('totalSpending').textContent = '₱' + totalSpending.toFixed(2);

            // Load returns to get stats
            const returnsRes = await axios.get('/api/return-items');
            const returns = returnsRes.data.data || returnsRes.data || [];
            document.getElementById('totalReturns').textContent = returns.length;
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
@endsection