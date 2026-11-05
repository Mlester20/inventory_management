@extends('layout.app')

@section('title', 'Edit Goods Receipt ' . $goodsReceipt->gr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('goods-receipts.show', $goodsReceipt) }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to {{ $goodsReceipt->gr_no }}
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('goods-receipts.apply-revision', $goodsReceipt) }}" method="POST" id="reviseGrForm">
        @csrf
        <div class="card mb-4">
            <h5 class="card-header">Edit {{ $goodsReceipt->gr_no }}</h5>
            <div class="card-body">
                <p class="text-muted small">
                    Correct what was received. The Warehouse stock follows: a different quantity adds or takes the difference, and a
                    different lot moves the whole line to that lot. Stock that is no longer in the Warehouse (delivered, sold,
                    transferred or adjusted since) cannot be taken out, so those lines cannot be lowered. The quantity of a line
                    against a Purchase Order also updates what that order has received. Lines cannot be added or removed.
                </p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="receiptDate" class="form-label">Receipt Date</label>
                        <input type="date" id="receiptDate" name="receipt_date" class="form-control"
                            value="{{ old('receipt_date', $goodsReceipt->receipt_date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="supplierId" class="form-label">Supplier</label>
                        @if($goodsReceipt->purchase_order_id)
                            <input type="text" class="form-control" value="{{ $goodsReceipt->supplier->supplier_name ?? '—' }}" disabled>
                            <div class="form-text">Set by {{ $goodsReceipt->purchaseOrder->po_no ?? 'the Purchase Order' }}.</div>
                        @else
                            <select id="supplierId" name="supplier_id" class="form-select">
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $goodsReceipt->supplier_id) === $supplier->id)>{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label for="preparedBy" class="form-label">Prepared By</label>
                        <select id="preparedBy" name="prepared_by" class="form-select">
                            @foreach($preparers as $preparer)
                                <option value="{{ $preparer->id }}" @selected((int) old('prepared_by', $goodsReceipt->prepared_by) === $preparer->id)>{{ $preparer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="table-header-bg">
                            <th>Item Description</th>
                            <th style="width: 110px;">Qty</th>
                            <th style="width: 130px;">Unit Cost</th>
                            <th style="width: 160px;">Lot/Batch</th>
                            <th style="width: 160px;">Expiry</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($goodsReceipt->items as $line)
                            @php $key = "lines.{$line->id}"; @endphp
                            <tr>
                                <td>
                                    {{ $line->productBatch?->product?->display_description ?? $line->product?->display_description ?? '—' }}
                                    @if($line->purchaseOrderItem)
                                        <span class="d-block text-muted small">Against the Purchase Order</span>
                                    @endif
                                </td>
                                <td>
                                    <input type="number" min="1" class="form-control form-control-sm text-end @error($key . '.qty') is-invalid @enderror"
                                        name="lines[{{ $line->id }}][qty]" value="{{ old($key . '.qty', $line->qty) }}" required>
                                </td>
                                <td>
                                    <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end"
                                        name="lines[{{ $line->id }}][unit_cost]" value="{{ old($key . '.unit_cost', $line->unit_cost) }}">
                                </td>
                                <td>
                                    <input type="text" maxlength="255" class="form-control form-control-sm"
                                        name="lines[{{ $line->id }}][batch_no]" value="{{ old($key . '.batch_no', $line->batch_no) }}">
                                </td>
                                <td>
                                    <input type="date" class="form-control form-control-sm"
                                        name="lines[{{ $line->id }}][expiration_date]" value="{{ old($key . '.expiration_date', $line->expiration_date?->toDateString()) }}">
                                </td>
                                <td>
                                    <input type="text" maxlength="255" class="form-control form-control-sm"
                                        name="lines[{{ $line->id }}][remarks]" value="{{ old($key . '.remarks', $line->remarks) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                <label for="reviseReason" class="form-label">Reason</label>
                <textarea id="reviseReason" name="reason" class="form-control" rows="2" maxlength="255"
                    placeholder="e.g. Wrong lot number typed" required>{{ old('reason') }}</textarea>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('goods-receipts.show', $goodsReceipt) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection
