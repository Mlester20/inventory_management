@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Quote ' . $salesQuote->quote_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('sales-quotes.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Sales Quotes
        </a>
        <div class="d-flex gap-2">
            @if($salesQuote->isDraft())
                <a href="{{ route('sales-quotes.edit', $salesQuote) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                @if(Auth::user()->role === 'admin')
                    <form action="{{ route('sales-quotes.destroy', $salesQuote) }}" method="POST" onsubmit="return confirmSubmit(this, 'Delete draft {{ $salesQuote->quote_no }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-trash"></i> Delete Draft
                        </button>
                    </form>
                @endif
            @else
                @if($salesQuote->status === 'open')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#convertModal">
                        <i class="bx bx-transfer"></i> Convert to Sales Order
                    </button>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
            @endif
            @if($salesQuote->isArchived())
                <form action="{{ route('sales-quotes.unarchive', $salesQuote) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bx bx-undo"></i> Unarchive
                    </button>
                </form>
            @else
                <form action="{{ route('sales-quotes.archive', $salesQuote) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bx bx-archive"></i> Archive
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger no-print">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="printableSalesQuote" class="no-print">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Quote No.</label>
                    <p class="fw-bold mb-0">{{ $salesQuote->quote_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $salesQuote->customer?->customer_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Valid Until</label>
                    <p class="fw-bold mb-0">{{ $salesQuote->valid_until?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        @if($salesQuote->isDraft())
                            <span class="badge bg-secondary">DRAFT</span>
                        @else
                            <span class="badge bg-{{ ['open' => 'warning', 'converted' => 'success', 'cancelled' => 'danger'][$salesQuote->status] ?? 'secondary' }}">
                                {{ ucfirst($salesQuote->status) }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Quote Date</label>
                    <p class="mb-0">{{ $salesQuote->quote_date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $salesQuote->preparedBy->name ?? '—' }}</p>
                </div>
                @if($salesQuote->salesOrder)
                    <div class="col-md-3">
                        <label class="text-muted small">Converted Sales Order</label>
                        <p class="mb-0">
                            <a href="{{ route('sales-orders.show', $salesQuote->salesOrder) }}">{{ $salesQuote->salesOrder->so_no }}</a>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Line Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Generic Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesQuote->items as $item)
                        <tr>
                            <td>{{ $item->genericName->generic_name ?? '—' }} ({{ $item->genericName->unit ?? '—' }})</td>
                            <td class="text-end">{{ $item->qty ?? '—' }}</td>
                            <td class="text-end">{{ $item->price !== null ? number_format($item->price, 2) : '—' }}</td>
                            <td class="text-end">{{ ($item->qty !== null && $item->price !== null) ? number_format($item->qty * $item->price, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-info fw-bold">
                        <td colspan="3">TOTAL</td>
                        <td class="text-end">{{ number_format($salesQuote->items->sum(fn($i) => ($i->qty ?? 0) * ($i->price ?? 0)), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    </div>

    <div class="card sq-print-only" id="printableSalesQuoteSheet">
        <div class="card-body p-4 sq-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'QUOTATION',
                'docNoLabel' => 'R.F.Q No.',
                'docNo' => $salesQuote->quote_no,
                'docDate' => $salesQuote->quote_date->format('m/d/Y'),
                'toHeader' => 'Customer',
                'toRows' => [
                    'Name' => $salesQuote->customer->customer_name ?? '',
                    'Address' => $salesQuote->customer->delivery_address ?? '',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Generic Description</th>
                            <th>Remarks</th>
                            <th style="width: 7%;">Unit</th>
                            <th class="text-end" style="width: 8%;">Qty</th>
                            <th class="text-end" style="width: 11%;">Unit Cost</th>
                            <th class="text-end" style="width: 13%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesQuote->items as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->genericName->generic_name ?? '—' }}</td>
                                <td>{{ $item->remarks ?? '—' }}</td>
                                <td class="text-center">{{ $item->genericName->unit ?? '—' }}</td>
                                <td class="text-end">{{ $item->qty }}</td>
                                <td class="text-end">{{ number_format($item->price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->qty * $item->price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.sales-totals-footer', [
                'totalAmountDue' => number_format($salesQuote->items->sum(fn($i) => ($i->qty ?? 0) * ($i->price ?? 0)), 2),
                'preparedByValue' => $salesQuote->preparedBy->name ?? '',
            ])
        </div>
    </div>

    @if($salesQuote->status === 'open' && ! $salesQuote->isDraft())
        <div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('sales-quotes.convert', $salesQuote) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Convert to Sales Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="order_date" class="form-label">Order Date</label>
                                <input
                                    type="date"
                                    name="order_date"
                                    id="order_date"
                                    class="form-control"
                                    value="{{ now()->toDateString() }}"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="convert_prepared_by" class="form-label">Prepared By</label>
                                <select name="prepared_by" id="convert_prepared_by" class="form-select">
                                    <option value="">-- Select User --</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ auth()->id() == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Convert</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

<style>
    @include('partials.print.base-print')

    .table-header-bg {
        background-color: #f7f8fa;
    }
    .table-info {
        background-color: #e7f3ff;
    }

    .sq-print-only {
        display: none;
    }

    .sq-sheet {
        font-size: 0.85rem;
    }

    @media print {
        #printableSalesQuoteSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableSalesQuoteSheet .card-body {
            padding: 0 !important;
        }

        .sq-print-only {
            display: block !important;
        }

        .table-header-bg,
        .table-info {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
