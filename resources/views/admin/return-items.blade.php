@extends('layout.app')

@section('title', 'Return Items')

@section('content')
<div class="card">
    <h5 class="card-header">Return Items</h5>
    <div class="table-responsive nowrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Return ID</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Return Date</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($returnItems as $returnItem)
                <tr>
                    <td>{{ $returnItem->id }}</td>
                    <td>{{ $returnItem->item->item_name }}</td>
                    <td>{{ $returnItem->quantity }}</td>
                    <td>{{ $returnItem->return_date }}</td>
                    <td>{{ $returnItem->reason }}</td>
                    <td>
                        <span class="badge bg-{{ $returnItem->status === 'approved' ? 'success' : ($returnItem->status === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($returnItem->status) }}
                        </span>
                    </td>
                    <td>
                        @if($returnItem->status === 'pending')
                            <form method="POST" action="{{ route('return-items.approve', $returnItem->id) }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to approve this return?');">
                                    Approve
                                </button>
                            </form>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $returnItem->id }}">
                                Reject
                            </button>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal{{ $returnItem->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Return Item #{{ $returnItem->id }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('return-items.reject', $returnItem->id) }}">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="rejection_reason{{ $returnItem->id }}" class="form-label">Rejection Reason</label>
                                                    <textarea class="form-control" id="rejection_reason{{ $returnItem->id }}" name="rejection_reason" rows="3" placeholder="Enter reason for rejection (optional)"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted">No actions available</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        });
    });
</script>
@endsection