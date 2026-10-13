@extends('layout.app')

@section('title', 'Purchase Orders — ' . $supplier->supplier_name)

@section('content')
@php
    $badge = ['COMPLETED' => 'bg-success', 'PARTIALLY RECEIVED' => 'bg-info', 'OPEN' => 'bg-warning'];
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <a href="{{ route('po-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Purchase Order Summary</a>
            <h4 class="mb-1">Purchase Orders &mdash; {{ $supplier->supplier_name }}</h4>
            <p class="text-muted small mb-0">Every Purchase Order of this supplier.</p>
        </div>
        <form method="GET" action="{{ route('po-summary.orders') }}" class="d-flex gap-2">
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
            <input type="text" name="search" autocomplete="off" value="{{ $search }}" class="form-control" placeholder="Search by P.O #" style="min-width: 220px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
            @if($search !== '')
                <a href="{{ route('po-summary.orders', ['supplier_id' => $supplier->id]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <h5 class="card-header">Purchase Orders ({{ $orders->count() }})</h5>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>P.O. No.</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php $status = \App\Services\PurchaseOrderSummaryService::statusLabel($order); @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><a href="{{ route('purchase-orders.show', $order) }}">{{ $order->po_no }}</a></td>
                            <td>{{ $order->supplier->supplier_name ?? '' }}</td>
                            <td>{{ $order->order_date?->format('M d, Y') }}</td>
                            <td><span class="badge {{ $badge[$status] }}">{{ $status }}</span></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('purchase-orders.show', $order) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View
                                        </a>
                                        <a href="{{ route('po-summary.receipts', ['purchase_order_id' => $order->id]) }}" class="dropdown-item">
                                            <i class="bx bx-package me-1"></i> Goods Receipts
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No Purchase Orders{{ $search !== '' ? ' for this search' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
