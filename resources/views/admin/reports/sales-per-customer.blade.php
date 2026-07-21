@extends('layout.app')

@section('title', 'Sales Per Customer Report')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Sales Per Customer Report</h4>
            <p class="text-muted">Two views: invoice customer names (free text) and real Customer records (delivered Sales Orders)</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.sales-per-customer') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.reports.sales-per-customer') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- By Invoice Customer Name -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0 d-flex align-items-center justify-content-between">
                <span>By Sales Invoice — Customer Name</span>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#invoiceNameNote" aria-expanded="false">
                    <i class="bx bx-info-circle"></i> Note
                </button>
            </h5>
        </div>
        <div id="invoiceNameNote" class="collapse">
            <div class="card-body pt-0">
                <p class="text-muted small mb-0">
                    Grouped by the free-text <code>customer_name</code> field on invoices — not a real customer relation.
                    Two different spellings of the same customer won't be merged here. A future link from Invoice to the
                    <code>customers</code> table could replace this with a proper customer_id-based grouping.
                </p>
            </div>
        </div>
        <div class="card-body">
            @forelse ($byInvoiceName as $row)
                @if ($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Customer Name</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Total Spent</th>
                                <th>Last Purchase</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td>{{ $row->customer_name }}</td>
                                <td class="text-end">{{ intval($row->transactions) }}</td>
                                <td class="text-end">{{ number_format($row->total_spent, 2) }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->last_purchase_at)->format('M d, Y') }}</td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                </div>
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No invoices for this period.
                </div>
            @endforelse
        </div>
    </div>

    <!-- By Real Customer Record -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0 d-flex align-items-center justify-content-between">
                <span>By Customer Record — Delivered Sales Orders</span>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#customerRecordNote" aria-expanded="false">
                    <i class="bx bx-info-circle"></i> Note
                </button>
            </h5>
        </div>
        <div id="customerRecordNote" class="collapse">
            <div class="card-body pt-0">
                <p class="text-muted small mb-0">
                    Grouped by the real <code>customers</code> record, using delivered quantity &times; the agreed
                    Sales Order price for each Delivery Receipt line. Standalone Advance Order deliveries with no
                    linked Sales Order line carry no price and are not counted here.
                </p>
            </div>
        </div>
        <div class="card-body">
            @forelse ($byCustomerRecord as $row)
                @if ($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Customer</th>
                                <th class="text-end">Deliveries</th>
                                <th class="text-end">Total Spent</th>
                                <th>Last Delivery</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td>{{ $row->customer_name }}</td>
                                <td class="text-end">{{ intval($row->transactions) }}</td>
                                <td class="text-end">{{ number_format($row->total_spent, 2) }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->last_purchase_at)->format('M d, Y') }}</td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                </div>
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No delivered Sales Order revenue for this period.
                </div>
            @endforelse
        </div>
    </div>

</div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>

@endsection
