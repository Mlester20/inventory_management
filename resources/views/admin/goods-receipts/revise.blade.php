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
                    <div class="col-md-6">
                        <label for="receiptDate" class="form-label">Receipt Date</label>
                        <input type="date" id="receiptDate" name="receipt_date" class="form-control"
                            value="{{ old('receipt_date', $goodsReceipt->receipt_date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="preparedBy" class="form-label">Prepared By</label>
                        <select id="preparedBy" name="prepared_by" class="form-select">
                            @foreach($preparers as $preparer)
                                <option value="{{ $preparer->id }}" @selected((int) old('prepared_by', $goodsReceipt->prepared_by) === $preparer->id)>{{ $preparer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body border-top p-0">
                <div class="p-3">
                    @foreach($goodsReceipt->items as $line)
                        @php $key = "lines.{$line->id}"; @endphp
                        <div class="line-item-card border rounded p-3 mb-3 bg-light">
                            <div class="mb-2">
                                <span class="fw-semibold text-muted">Item</span>
                                <div class="mt-1">
                                    {{ $line->productBatch?->product?->display_description ?? $line->product?->display_description ?? '—' }}
                                    @if($line->purchaseOrderItem)
                                        <span class="d-block text-muted small">Against the Purchase Order</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Supplier (optional)</label>
                                    <select name="lines[{{ $line->id }}][supplier_id]" class="form-select line-supplier-select"
                                        title="{{ $line->supplier?->supplier_name ?? '' }}" onchange="this.title=this.options[this.selectedIndex].text"
                                        @disabled((bool) old($key . '.supplier_not_determined', $line->supplier_not_determined))>
                                        <option value="">-- No Supplier --</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}"
                                                @selected((string) old($key . '.supplier_id', $line->supplier?->id) === (string) $supplier->id)>
                                                {{ $supplier->supplier_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-2 d-flex align-items-end pb-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="lines[{{ $line->id }}][supplier_not_determined]" value="1"
                                            class="form-check-input" id="supplierNotDetermined{{ $line->id }}"
                                            @checked((bool) old($key . '.supplier_not_determined', $line->supplier_not_determined))
                                            onchange="const card=this.closest('.line-item-card'); const select=card.querySelector('.line-supplier-select'); if(this.checked) select.value=''; select.disabled=this.checked">
                                        <label class="form-check-label small" for="supplierNotDetermined{{ $line->id }}">Supplier Not Determined</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">Qty</label>
                                    <input type="number" min="1" class="form-control text-end @error($key . '.qty') is-invalid @enderror"
                                        name="lines[{{ $line->id }}][qty]" value="{{ old($key . '.qty', $line->qty) }}" required>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-1">Unit Cost</label>
                                    <input type="number" min="0" step="0.01" class="form-control text-end"
                                        name="lines[{{ $line->id }}][unit_cost]" value="{{ old($key . '.unit_cost', $line->unit_cost) }}">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small mb-1">Lot/Batch</label>
                                    <input type="text" maxlength="255" class="form-control"
                                        name="lines[{{ $line->id }}][batch_no]" value="{{ old($key . '.batch_no', $line->batch_no) }}">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small mb-1">Expiry</label>
                                    <input type="date" class="form-control"
                                        name="lines[{{ $line->id }}][expiration_date]" value="{{ old($key . '.expiration_date', $line->expiration_date?->toDateString()) }}">
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Remarks</label>
                                    <input type="text" maxlength="255" class="form-control"
                                        name="lines[{{ $line->id }}][remarks]" value="{{ old($key . '.remarks', $line->remarks) }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
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
