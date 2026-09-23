@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Draft Invoice ' . $invoice->sales_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Invoices
        </a>
        <div class="d-flex gap-2">
            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary">
                <i class="bx bx-edit-alt"></i> Continue Editing
            </a>
            @if(Auth::user()->role === 'admin')
                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" onsubmit="return confirmSubmit(this, 'Delete draft {{ $invoice->sales_no }}? This can be restored from the trash later.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="alert alert-info">
        This invoice is a <strong>draft</strong>. It hasn't deducted any stock or counted toward any sales total yet —
        the VAT breakdown and Amount Due are worked out when it is posted.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Sales No.</label>
                    <p class="fw-bold mb-0">{{ $invoice->sales_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $invoice->customer_name !== '' ? $invoice->customer_name : '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">P.O. No.</label>
                    <p class="mb-0">{{ $invoice->po_no ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0"><span class="badge bg-secondary">DRAFT</span></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">SC / PWD ID No.</label>
                    <p class="mb-0">{{ $invoice->osca_no ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Withholding Tax</label>
                    <p class="mb-0">₱{{ number_format($invoice->less_wt, 2) }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $invoice->preparedBy->name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Approved By</label>
                    <p class="mb-0">{{ $invoice->approved_by ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Line Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Item</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Discount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoice->draftItems as $line)
                        <tr>
                            <td>{{ $line->product->item_name ?? '—' }}</td>
                            <td>{{ $line->desc ?? '—' }}</td>
                            <td>{{ $line->unit ?? '—' }}</td>
                            <td class="text-end">{{ $line->qty ?? '—' }}</td>
                            <td class="text-end">{{ $line->price !== null ? number_format($line->price, 2) : '—' }}</td>
                            <td class="text-end">{{ $line->dis !== null ? number_format($line->dis, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No items saved on this draft yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>
@endsection
