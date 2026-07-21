@extends('layout.app')

@section('title', 'Goods Receipts')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('goods-receipts.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by supplier or G.R. no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('goods-receipts.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('goods-receipts.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Goods Receipt
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Goods Receipts</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>G.R. No.</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th>Purchase Order</th>
                        <th>Receipt Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($goodsReceipts as $goodsReceipt)
                        <tr>
                            <td>{{ $goodsReceipt->id }}</td>
                            <td>{{ $goodsReceipt->gr_no }}</td>
                            <td>{{ $goodsReceipt->supplier->supplier_name }}</td>
                            <td>
                                <span class="badge bg-{{ $goodsReceipt->purchase_order_id ? 'info' : 'secondary' }}">
                                    {{ $goodsReceipt->purchase_order_id ? 'Against P.O.' : 'Direct Receipt' }}
                                </span>
                            </td>
                            <td>
                                @if($goodsReceipt->purchaseOrder)
                                    <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}">{{ $goodsReceipt->purchaseOrder->po_no }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $goodsReceipt->receipt_date->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('goods-receipts.show', $goodsReceipt) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No Goods Receipts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($goodsReceipts->hasPages())
            <div class="card-footer">
                {{ $goodsReceipts->links() }}
            </div>
        @endif
    </div>
@endsection
