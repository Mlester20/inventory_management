@extends('layout.app')

@section('title', 'New Purchase Invoice')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Purchase Invoice</h5>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('purchase-invoices.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">-- Select Supplier --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="prepared_by" class="form-label">Prepared By</label>
                        <select name="prepared_by" id="prepared_by" class="form-select">
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="goods_receipt_id" class="form-label">Goods Receipt <span class="text-muted small">(optional)</span></label>
                        <select name="goods_receipt_id" id="goods_receipt_id" class="form-select">
                            <option value="">-- None --</option>
                        </select>
                        <div class="form-text">Select a supplier first to see its Goods Receipts.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="purchase_order_id" class="form-label">Purchase Order <span class="text-muted small">(optional)</span></label>
                        <select name="purchase_order_id" id="purchase_order_id" class="form-select">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="invoice_no" class="form-label">Invoice No.</label>
                        <input
                            type="text"
                            name="invoice_no"
                            id="invoice_no"
                            class="form-control @error('invoice_no') is-invalid @enderror"
                            placeholder="e.g., SI-00123 (supplier's own reference)"
                            value="{{ old('invoice_no') }}"
                            required
                        >
                        @error('invoice_no')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="invoice_date" class="form-label">Invoice Date</label>
                        <input
                            type="date"
                            name="invoice_date"
                            id="invoice_date"
                            class="form-control @error('invoice_date') is-invalid @enderror"
                            value="{{ old('invoice_date', now()->toDateString()) }}"
                            required
                        >
                        @error('invoice_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            id="amount"
                            class="form-control @error('amount') is-invalid @enderror"
                            placeholder="e.g., 15000.00"
                            value="{{ old('amount') }}"
                            required
                        >
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="vat_amount" class="form-label">VAT Amount <span class="text-muted small">(optional)</span></label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="vat_amount"
                            id="vat_amount"
                            class="form-control"
                            placeholder="e.g., 1607.14"
                            value="{{ old('vat_amount') }}"
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label for="remarks" class="form-label">Remarks <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="remarks" id="remarks" class="form-control" placeholder="e.g., Covers partial delivery of PO-2026-00045" value="{{ old('remarks') }}">
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Purchase Invoice</button>
                    <a href="{{ route('purchase-invoices.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const GOODS_RECEIPTS = @json($goodsReceipts);
    const PURCHASE_ORDERS = @json($purchaseOrders);

    const supplierSelect = document.getElementById('supplier_id');
    const goodsReceiptSelect = document.getElementById('goods_receipt_id');
    const purchaseOrderSelect = document.getElementById('purchase_order_id');
    const amountInput = document.getElementById('amount');

    function refreshGoodsReceiptOptions() {
        const supplierId = supplierSelect.value;
        goodsReceiptSelect.innerHTML = '<option value="">-- None --</option>';
        GOODS_RECEIPTS
            .filter(gr => String(gr.supplier_id) === String(supplierId))
            .forEach(gr => {
                const option = document.createElement('option');
                option.value = gr.id;
                option.textContent = gr.gr_no;
                option.dataset.total = gr.total;
                option.dataset.purchaseOrderId = gr.purchase_order_id || '';
                goodsReceiptSelect.appendChild(option);
            });
    }

    function refreshPurchaseOrderOptions() {
        const supplierId = supplierSelect.value;
        purchaseOrderSelect.innerHTML = '<option value="">-- None --</option>';
        PURCHASE_ORDERS
            .filter(po => String(po.supplier_id) === String(supplierId))
            .forEach(po => {
                const option = document.createElement('option');
                option.value = po.id;
                option.textContent = po.po_no;
                purchaseOrderSelect.appendChild(option);
            });
    }

    supplierSelect.addEventListener('change', function () {
        refreshGoodsReceiptOptions();
        refreshPurchaseOrderOptions();
    });

    // Selecting a Goods Receipt prefills the amount from its received total
    // and links the Purchase Order it was received against, if any — both
    // stay editable in case the supplier's actual bill differs.
    goodsReceiptSelect.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        if (!selected || !selected.value) {
            return;
        }

        const total = parseFloat(selected.dataset.total || '0');
        if (total > 0) {
            amountInput.value = total.toFixed(2);
        }

        const poId = selected.dataset.purchaseOrderId;
        if (poId) {
            purchaseOrderSelect.value = poId;
        }
    });

    if (supplierSelect.value) {
        refreshGoodsReceiptOptions();
        refreshPurchaseOrderOptions();
    }
</script>
@endsection
