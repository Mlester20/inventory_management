@extends('layout.app')

@section('title', 'New Inventory Adjustment')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Inventory Adjustment</h5>
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

            <form action="{{ route('inventory-adjustments.store') }}" method="POST" id="adjustmentForm">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="adjustment_date" class="form-control" value="{{ old('adjustment_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            @foreach (\App\Models\InventoryAdjustment::TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Prepared By</label>
                        <select name="prepared_by" class="form-select">
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Line Items</h6>
                    <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Line
                    </button>
                </div>

                <div id="lineItemsBody"></div>

                <div class="mb-3 mt-3">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('inventory-adjustments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const PRODUCTS = @json($productsForJs);

    const preselectedProductId = @json($preselectedProductId);
    let rowIndex = 0;

    function productOptions(selectedId = '') {
        let html = '<option value="">-- Select Item --</option>';
        PRODUCTS.forEach(p => {
            const selected = String(p.id) === String(selectedId) ? 'selected' : '';
            html += `<option value="${p.id}" ${selected}>${p.name}</option>`;
        });
        return html;
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Line #' + (i + 1);
        });
    }

    function addRow(productId = '') {
        const index = rowIndex++;
        const card = document.createElement('div');
        card.className = 'line-item-card border rounded p-3 mb-3';
        card.dataset.index = index;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted line-item-number">Line</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Item Description</label>
                    <select name="lines[${index}][product_id]" class="form-select product-select" required>
                        ${productOptions(productId)}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Lot/Batch No.</label>
                    <input type="text" name="lines[${index}][batch_no]" class="form-control batch-input" list="batch-list-${index}">
                    <datalist id="batch-list-${index}"></datalist>
                    <input type="hidden" name="lines[${index}][product_batch_id]" class="batch-id-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Expiry Date</label>
                    <input type="date" name="lines[${index}][expiration_date]" class="form-control expiry-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="lines[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Unit</label>
                    <input type="text" class="form-control unit-display" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Remarks</label>
                    <input type="text" name="lines[${index}][remarks]" class="form-control">
                </div>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(card);
        bindRowEvents(card);
        renumberRows();

        if (productId) {
            card.querySelector('.product-select').dispatchEvent(new Event('change'));
        }
    }

    function bindRowEvents(card) {
        const productSelect = card.querySelector('.product-select');
        const unitDisplay = card.querySelector('.unit-display');
        const batchInput = card.querySelector('.batch-input');
        const batchIdInput = card.querySelector('.batch-id-input');
        const expiryInput = card.querySelector('.expiry-input');
        const datalist = card.querySelector('datalist');

        productSelect.addEventListener('change', function () {
            const product = PRODUCTS.find(p => String(p.id) === String(this.value));
            unitDisplay.value = product ? product.unit : '';
            datalist.innerHTML = '';
            batchIdInput.value = '';

            if (product) {
                product.batches.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.batch_no || '';
                    datalist.appendChild(opt);
                });
            }
        });

        batchInput.addEventListener('input', function () {
            const product = PRODUCTS.find(p => String(p.id) === String(productSelect.value));
            batchIdInput.value = '';
            if (!product) return;

            const match = product.batches.find(b => b.batch_no === this.value);
            if (match) {
                batchIdInput.value = match.id;
                expiryInput.value = match.expiration_date || '';
            }
        });

        card.querySelector('.remove-row-btn').addEventListener('click', function () {
            card.remove();
            renumberRows();
        });
    }

    document.getElementById('addRowBtn').addEventListener('click', () => addRow());

    // Start with one row, pre-selecting the product if launched from the
    // Lot/Serial & Expiry tab's "Adjust Inventory" button.
    addRow(preselectedProductId);
</script>
@endsection
