@extends('layout.app')

@section('title', 'Purchase Invoices')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('purchase-invoices.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by supplier or invoice no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('purchase-invoices.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('purchase-invoices.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Purchase Invoice
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Purchase Invoices</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Invoice No.</th>
                        <th>Supplier</th>
                        <th>Invoice Date</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseInvoices as $purchaseInvoice)
                        <tr>
                            <td>{{ $purchaseInvoice->id }}</td>
                            <td>{{ $purchaseInvoice->invoice_no ?? '—' }}</td>
                            <td>{{ $purchaseInvoice->supplier->supplier_name ?? '—' }}</td>
                            <td>{{ $purchaseInvoice->invoice_date?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $purchaseInvoice->isDraft() ? 'secondary' : 'success' }}">
                                    {{ \App\Models\PurchaseInvoice::STATUSES[$purchaseInvoice->status] ?? $purchaseInvoice->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $purchaseInvoice->amount !== null ? number_format($purchaseInvoice->amount, 2) : '—' }}</td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('purchase-invoices.show', $purchaseInvoice) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View
                                        </a>
                                        @if($purchaseInvoice->isDraft())
                                            <a href="{{ route('purchase-invoices.edit', $purchaseInvoice) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                            </a>
                                        @endif

                                        <div class="dropdown-divider"></div>

                                        <form
                                            action="{{ route('purchase-invoices.destroy', $purchaseInvoice) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('Are you sure you want to delete this {{ $purchaseInvoice->isDraft() ? 'draft' : 'Purchase Invoice' }}?')"
                                            >
                                                <i class="bx bx-trash me-1"></i> {{ $purchaseInvoice->isDraft() ? 'Delete Draft' : 'Delete' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No Purchase Invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchaseInvoices->hasPages())
            <div class="card-footer">
                {{ $purchaseInvoices->links() }}
            </div>
        @endif
    </div>
@endsection
