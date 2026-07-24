@extends('layout.app')

@section('title', 'Delivery Receipts')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('delivery-receipts.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by customer or D.R. no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('delivery-receipts.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Delivery Receipt
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Delivery Receipts</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Delivery Date</th>
                        <th>Reference</th>
                        <th>Sales Order No.</th>
                        <th>Customer</th>
                        <th>Description</th>
                        <th>Transaction Type</th>
                        <th>Delivery Status</th>
                        <th>Invoice Status</th>
                        <th>Timestamp</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveryReceipts as $deliveryReceipt)
                        <tr>
                            <td>{{ $deliveryReceipt->id }}</td>
                            <td>{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</td>
                            <td>{{ $deliveryReceipt->dr_no }}</td>
                            <td>
                                @if($deliveryReceipt->salesOrder)
                                    <a href="{{ route('sales-orders.show', $deliveryReceipt->salesOrder) }}">{{ $deliveryReceipt->salesOrder->so_no }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $deliveryReceipt->customer->customer_name }}</td>
                            <td>{{ $deliveryReceipt->description ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ ['purchase_order' => 'info', 'walk_in' => 'warning'][$deliveryReceipt->transaction_type] ?? 'secondary' }}">
                                    {{ \App\Models\DeliveryReceipt::TRANSACTION_TYPES[$deliveryReceipt->transaction_type] ?? $deliveryReceipt->transaction_type }}
                                </span>
                            </td>
                            <td><span class="badge bg-success">{{ $deliveryReceipt->deliveryStatus() }}</span></td>
                            <td>
                                @php $invoiceStatusColor = ['COMPLETE' => 'success', 'PARTIALLY INVOICED' => 'warning', 'NOT INVOICED' => 'secondary'][$deliveryReceipt->invoice_status] ?? 'secondary'; @endphp
                                <span class="badge bg-{{ $invoiceStatusColor }}">{{ $deliveryReceipt->invoice_status }}</span>
                            </td>
                            <td>{{ $deliveryReceipt->created_at->format('m/d/Y h:i A') }}</td>
                            <td>
                                <a href="{{ route('delivery-receipts.show', $deliveryReceipt) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">No Delivery Receipts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($deliveryReceipts->hasPages())
            <div class="card-footer">
                {{ $deliveryReceipts->links() }}
            </div>
        @endif
    </div>
@endsection
