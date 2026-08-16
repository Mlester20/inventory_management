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
                        <th>Status</th>
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
                            <td>
                                <span class="badge bg-{{ $goodsReceipt->isDraft() ? 'secondary' : 'success' }}">
                                    {{ \App\Models\GoodsReceipt::STATUSES[$goodsReceipt->status] ?? $goodsReceipt->status }}
                                </span>
                            </td>
                            <td>{{ $goodsReceipt->supplier->supplier_name ?? '—' }}</td>
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
                            <td>{{ $goodsReceipt->receipt_date?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('goods-receipts.show', $goodsReceipt) }}" class="btn btn-sm btn-info">
                                        View
                                    </a>
                                    @if($goodsReceipt->isDraft())
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="{{ route('goods-receipts.edit', $goodsReceipt) }}" class="dropdown-item">
                                                    <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('goods-receipts.destroy', $goodsReceipt) }}" method="POST" class="m-0" onsubmit="return confirm('Delete draft {{ $goodsReceipt->gr_no }}? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bx bx-trash me-1"></i> Delete Draft
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No Goods Receipts found.</td>
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
