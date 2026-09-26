@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Invoice ' . $invoice->sales_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Invoices
        </a>
        <div class="d-flex gap-2">
            @if($invoice->is_personal_use)
                <span class="badge bg-label-info align-self-center">Personal use: no withholding tax</span>
            @endif
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bx bx-printer"></i> Print
            </button>
            @if($invoice->isArchived())
                <form action="{{ route('invoices.unarchive', $invoice) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bx bx-undo"></i> Unarchive
                    </button>
                </form>
            @else
                <form action="{{ route('invoices.archive', $invoice) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bx bx-archive"></i> Archive
                    </button>
                </form>
            @endif
            @if(Auth::user()->role === 'admin' && ! $invoice->isCancelled())
                <form action="{{ route('invoices.cancel', $invoice) }}" method="POST" onsubmit="return confirmSubmit(this, 'Cancel/void this invoice? The record and its recorded sales stay, only the status changes.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-block"></i> Cancel/Void
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card" id="printableInvoice">
        <div class="card-body p-4 invoice-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'SALES INVOICE',
                'docNoLabel' => 'S.I No.',
                'docNo' => $invoice->sales_no,
                'docNoLabel2' => 'P.O No.',
                'docNo2' => $invoice->po_no ?? null,
                'docDate' => $invoice->created_at->format('m/d/Y'),
                'toHeader' => 'Customer',
                'toRows' => [
                    'Name' => $invoice->customer_name,
                    'Address' => $invoice->customer?->delivery_address ?? '—',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Item Description</th>
                            <th style="width: 8%;">Unit</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th class="text-end" style="width: 10%;">Unit Cost</th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->sales as $sale)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $sale->productBatch->product->item_name ?? $sale->desc }}</td>
                                <td class="text-center">{{ $sale->unit ?? '—' }}</td>
                                <td class="text-end">{{ $sale->qty }}</td>
                                <td class="text-end">{{ number_format($sale->price, 2) }}</td>
                                <td class="text-end">{{ number_format($sale->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.sales-totals-footer', [
                'totalAmountDue' => number_format($invoice->amount_due, 2),
                'preparedByValue' => $invoice->preparedBy->name ?? '',
                // Unlike Sales Order/Sales Quote (still generic-level at
                // that stage), an Invoice's lines are always real Products
                // with a known tax_id by this point — either picked
                // directly (manual "New Invoice") or inherited from the
                // Delivery Receipt line's product_batch — so this is real,
                // reliably computed data, not a guess.
                'vatableSales' => $invoice->vat_sales,
                'vatExemptSales' => $invoice->vatex_sales,
                'vatZeroRated' => $invoice->zero_sales,
                'addVat' => $invoice->vat_amount,
                'lessWithholdingTax' => $invoice->less_wt,
            ])
        </div>
    </div>
@endsection

@section('scripts')
<style>
    @include('partials.print.base-print')

    .invoice-sheet {
        font-size: 0.68rem;
    }

    .invoice-sheet .print-company-detail {
        font-size: 0.6rem;
    }

    .invoice-sheet .print-doc-title {
        font-size: 1.3rem;
    }

    .invoice-sheet .print-doc-page,
    .invoice-sheet .print-doc-no-row {
        font-size: 0.62rem;
    }

    .invoice-sheet .print-to-header {
        font-size: 0.66rem;
    }

    .invoice-sheet .print-to-row {
        font-size: 0.62rem;
    }

    .invoice-sheet .print-sig-label {
        font-size: 0.66rem;
    }

    .invoice-sheet .print-sig-value {
        font-size: 0.6rem;
    }

    .invoice-sheet .print-items-table th,
    .invoice-sheet .print-items-table td {
        font-size: 0.62rem;
        padding: 0.15rem 0.3rem;
    }

    .invoice-sheet .print-note-label {
        font-size: 0.66rem;
    }

    .invoice-sheet .print-note-content,
    .invoice-sheet .print-vat-table td {
        font-size: 0.56rem;
    }

    @media print {
        #printableInvoice {
            box-shadow: none !important;
            border: none !important;
        }

        #printableInvoice .card-body {
            padding: 0 !important;
        }
    }
</style>
@endsection
