@extends('layout.app')

@section('title', 'Return Items')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Return Items</h5>
        <a href="{{ route('return-items.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Return
        </a>
    </div>
    <div class="table-responsive nowrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Return ID</th>
                    <th>Item Name</th>
                    <th>Customer</th>
                    <th>Quantity</th>
                    <th>Return Date</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Stock</th>
                    <th>Refund</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returnItems as $returnItem)
                <tr>
                    <td>{{ $returnItem->id }}</td>
                    <td>{{ $returnItem->productBatch->product->item_name }}</td>
                    <td>{{ $returnItem->customer->customer_name ?? '—' }}</td>
                    <td>{{ $returnItem->quantity }}</td>
                    <td>{{ $returnItem->return_date }}</td>
                    <td>{{ $returnItem->reason }}</td>
                    <td>
                        <span class="badge bg-{{ $returnItem->status === 'approved' ? 'success' : ($returnItem->status === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($returnItem->status) }}
                        </span>
                    </td>
                    <td>
                        @if($returnItem->stock_disposition)
                            <span class="badge bg-{{ $returnItem->stock_disposition === 'sellable' ? 'success' : 'dark' }}">
                                {{ \App\Models\ReturnItem::STOCK_DISPOSITIONS[$returnItem->stock_disposition] ?? $returnItem->stock_disposition }}
                            </span>
                            @if($returnItem->stock_disposal_id)
                                <div class="small"><a href="{{ route('stock-disposals.show', $returnItem->stock_disposal_id) }}">{{ $returnItem->stockDisposal->reference ?? '' }}</a></div>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($returnItem->refund_method)
                            <span class="badge bg-{{ $returnItem->refund_method === 'credit' ? 'info' : 'secondary' }}">
                                {{ \App\Models\ReturnItem::REFUND_METHODS[$returnItem->refund_method] ?? $returnItem->refund_method }}
                            </span>
                            <div class="small text-muted">₱{{ number_format($returnItem->refund_amount, 2) }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($returnItem->status === 'pending')
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal{{ $returnItem->id }}">
                                Approve
                            </button>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $returnItem->id }}">
                                Reject
                            </button>

                            <!-- Approve Modal -->
                            <div class="modal fade" id="approveModal{{ $returnItem->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve Return Item #{{ $returnItem->id }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('return-items.approve', $returnItem->id) }}" class="approve-form">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="customer_id{{ $returnItem->id }}" class="form-label">Customer (optional)</label>
                                                    <select class="form-select customer-select" id="customer_id{{ $returnItem->id }}" name="customer_id">
                                                        <option value="">— No customer —</option>
                                                        @foreach($customers as $customer)
                                                            <option value="{{ $customer->id }}" {{ $returnItem->customer_id === $customer->id ? 'selected' : '' }}>{{ $customer->customer_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-1">
                                                    <label class="form-label d-block">Refund Method</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input refund-method-input" type="radio" name="refund_method" id="refundCash{{ $returnItem->id }}" value="cash" checked>
                                                        <label class="form-check-label" for="refundCash{{ $returnItem->id }}">
                                                            Cash Refund — money is handed back now; no credit recorded.
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input refund-method-input" type="radio" name="refund_method" id="refundCredit{{ $returnItem->id }}" value="credit">
                                                        <label class="form-check-label" for="refundCredit{{ $returnItem->id }}">
                                                            Store Credit — added to the customer's Available Credit for future orders.
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="form-text credit-requires-customer-hint d-none text-danger mb-3">Select a customer above to store this as credit.</div>

                                                <div class="mb-1">
                                                    <label class="form-label d-block">Stock Disposition</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="stock_disposition" id="dispositionSellable{{ $returnItem->id }}" value="sellable" {{ $returnItem->suggestedStockDisposition() === 'sellable' ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="dispositionSellable{{ $returnItem->id }}">
                                                            Sellable — restock to POS as available inventory.
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="stock_disposition" id="dispositionWriteOff{{ $returnItem->id }}" value="write_off" {{ $returnItem->suggestedStockDisposition() === 'write_off' ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="dispositionWriteOff{{ $returnItem->id }}">
                                                            Not sellable — write off (won't count as available stock).
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="form-text">Inspect the returned item before approving — the option above is only pre-selected from the return's stated reason.</div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Approve</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

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
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">No return items yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($returnItems->hasPages())
        <div class="card-footer">
            {{ $returnItems->links() }}
        </div>
    @endif
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

    // Store Credit requires a customer — if none is picked, block submit
    // with an inline hint instead of letting the server round-trip reject it.
    document.querySelectorAll('.approve-form').forEach(function (form) {
        const customerSelect = form.querySelector('.customer-select');
        const hint = form.querySelector('.credit-requires-customer-hint');

        function creditRadio() {
            return form.querySelector('.refund-method-input[value="credit"]');
        }

        function syncHint() {
            const wantsCredit = creditRadio().checked;
            hint.classList.toggle('d-none', !(wantsCredit && !customerSelect.value));
        }

        customerSelect.addEventListener('change', syncHint);
        form.querySelectorAll('.refund-method-input').forEach(function (radio) {
            radio.addEventListener('change', syncHint);
        });

        form.addEventListener('submit', function (e) {
            if (creditRadio().checked && !customerSelect.value) {
                e.preventDefault();
                syncHint();
            }
        });
    });
</script>
@endsection