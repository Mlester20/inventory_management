@extends('layout.app')

@section('title', 'New Purchase Order')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Purchase Order</h5>
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

            <form action="{{ route('purchase-orders.store') }}" method="POST" id="purchaseOrderForm">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select
                            name="supplier_id"
                            id="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror"
                            required
                        >
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

                    <div class="col-md-4 mb-3">
                        <label for="order_date" class="form-label">Order Date</label>
                        <input
                            type="date"
                            name="order_date"
                            id="order_date"
                            class="form-control @error('order_date') is-invalid @enderror"
                            value="{{ old('order_date', now()->toDateString()) }}"
                            required
                        >
                        @error('order_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="prepared_by" class="form-label">Prepared By</label>
                        <select
                            name="prepared_by"
                            id="prepared_by"
                            class="form-select @error('prepared_by') is-invalid @enderror"
                        >
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('prepared_by')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Line Items</h6>
                    <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Item
                    </button>
                </div>

                <div id="lineItemsBody"></div>

                <div class="row justify-content-end mt-4">
                    <div class="col-md-4">
                        <table class="table table-sm">
                            <tbody>
                                <tr class="fw-bold fs-5">
                                    <td>Total</td>
                                    <td class="text-end" id="grandTotal">₱0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Purchase Order</button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const ITEMS = @json($itemsForJs);

    let rowIndex = 0;

    function currentSupplierId() {
        return document.getElementById('supplier_id').value;
    }

    function itemOptions(selectedId = '') {
        let html = '<option value="">-- Select Item --</option>';
        const supplierId = currentSupplierId();

        ITEMS.forEach(item => {
            if (supplierId && String(item.supplier_id) !== String(supplierId)) {
                return;
            }
            const selected = String(item.id) === String(selectedId) ? 'selected' : '';
            html += `<option value="${item.id}" ${selected}>${item.name}</option>`;
        });
        return html;
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Item #' + (i + 1);
        });
    }

    function addRow() {
        const index = rowIndex++;
        const card = document.createElement('div');
        card.className = 'line-item-card border rounded p-3 mb-3';
        card.dataset.index = index;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted line-item-number">Item</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Item</label>
                    <select name="items[${index}][item_id]" class="form-select item-select" required>
                        ${itemOptions()}
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Unit Cost</label>
                    <input type="number" name="items[${index}][unit_cost]" class="form-control cost-input" step="0.01" min="0" value="0" required>
                </div>
            </div>

            <div class="text-end mt-2 pt-2 border-top">
                <span class="text-muted small">Line Amount:</span>
                <strong class="amount-display">₱0.00</strong>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(card);
        bindRowEvents(card);
        renumberRows();
        computeTotals();
    }

    function bindRowEvents(card) {
        const itemSelect = card.querySelector('.item-select');
        const costInput = card.querySelector('.cost-input');

        itemSelect.addEventListener('change', function () {
            const item = ITEMS.find(i => String(i.id) === String(this.value));
            if (item) {
                costInput.value = item.unit_cost.toFixed(2);
            }
            computeTotals();
        });

        card.querySelectorAll('.qty-input, .cost-input').forEach(el => {
            el.addEventListener('input', computeTotals);
        });

        card.querySelector('.remove-row-btn').addEventListener('click', function () {
            card.remove();
            renumberRows();
            computeTotals();
        });
    }

    function computeTotals() {
        let grandTotal = 0;

        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(card => {
            const qty = parseFloat(card.querySelector('.qty-input').value) || 0;
            const cost = parseFloat(card.querySelector('.cost-input').value) || 0;
            const amount = qty * cost;
            grandTotal += amount;
            card.querySelector('.amount-display').textContent = '₱' + amount.toFixed(2);
        });

        document.getElementById('grandTotal').textContent = '₱' + grandTotal.toFixed(2);
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    // Refresh each row's item list when the supplier changes, since items are
    // filtered to that supplier's catalog.
    document.getElementById('supplier_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(card => {
            const itemSelect = card.querySelector('.item-select');
            const currentValue = itemSelect.value;
            itemSelect.innerHTML = itemOptions(currentValue);
        });
    });

    // Start with one empty row
    addRow();
</script>
@endsection
