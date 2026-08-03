@extends('layout.app')

@section('title', 'New Stock Disposal')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Stock Disposal</h5>
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

            <form action="{{ route('stock-disposals.store') }}" method="POST" id="stockDisposalForm">
                @csrf

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <select name="reason" id="reason" class="form-select" required>
                            @foreach (\App\Models\StockDisposal::REASONS as $value => $label)
                                <option value="{{ $value }}" {{ old('reason', 'Expired') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
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
                    <div class="col-md-3 mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <input type="text" name="remarks" id="remarks" class="form-control" placeholder="e.g., Removed during shelf inspection" value="{{ old('remarks') }}">
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Items to Dispose</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="number" id="generateLinesInput" class="form-control form-control-sm" style="width: 90px;" min="1" max="100" placeholder="# lines">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="generateLinesBtn">
                            <i class="bx bx-list-plus"></i> Generate
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                            <i class="bx bx-plus"></i> Add Line
                        </button>
                    </div>
                </div>

                <div id="lineItemsBody"></div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Stock Disposal</button>
                    <a href="{{ route('stock-disposals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const PRODUCTS = @json($productsForJs);
    const preselectedBatchId = @json($preselectedBatchId);
    let rowIndex = 0;

    // Item Description is a searchable text field (native <datalist>,
    // matching the same technique already used in Inventory Adjustment)
    // rather than a long <select>.
    function productLabel(p) {
        return p.name;
    }

    function productDatalistOptions() {
        return PRODUCTS.map(p => `<option value="${productLabel(p)}"></option>`).join('');
    }

    function findProductByLabel(label) {
        return PRODUCTS.find(p => productLabel(p) === label);
    }

    function findBatchById(batchId) {
        for (const product of PRODUCTS) {
            const batch = product.batches.find(b => String(b.id) === String(batchId));
            if (batch) {
                return { product, batch };
            }
        }
        return null;
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Line #' + (i + 1);
        });
    }

    function locationOptionsHtml(locations) {
        if (locations.length === 0) {
            return '<option value="">-- No stock at any location --</option>';
        }
        return locations.map(loc => `<option value="${loc.location_id}" data-max="${loc.qty}">${loc.location_name} (Available: ${loc.qty})</option>`).join('');
    }

    function addRow() {
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
                    <input type="text" class="form-control product-search-input" list="product-list-${index}"
                        placeholder="Search item..." autocomplete="off" required>
                    <datalist id="product-list-${index}">${productDatalistOptions()}</datalist>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Lot/Batch No.</label>
                    <input type="text" class="form-control batch-input" list="batch-list-${index}" placeholder="Select item first">
                    <datalist id="batch-list-${index}" class="batch-datalist"></datalist>
                    <input type="hidden" name="lines[${index}][product_batch_id]" class="batch-id-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Expiry Date</label>
                    <input type="text" class="form-control expiry-display" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Location</label>
                    <select name="lines[${index}][location_id]" class="form-select location-select" required>
                        <option value="">-- Select batch first --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="lines[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(card);
        bindRowEvents(card);
        renumberRows();
    }

    function bindRowEvents(card) {
        const productSearchInput = card.querySelector('.product-search-input');
        const batchInput = card.querySelector('.batch-input');
        const batchIdInput = card.querySelector('.batch-id-input');
        const expiryDisplay = card.querySelector('.expiry-display');
        const batchDatalist = card.querySelector('.batch-datalist');
        const locationSelect = card.querySelector('.location-select');
        const qtyInput = card.querySelector('.qty-input');

        function resetBatch() {
            batchInput.value = '';
            batchIdInput.value = '';
            expiryDisplay.value = '';
            locationSelect.innerHTML = '<option value="">-- Select batch first --</option>';
        }

        function syncLocationMax() {
            const selected = locationSelect.options[locationSelect.selectedIndex];
            const max = selected ? parseInt(selected.getAttribute('data-max') || '0', 10) : 0;
            qtyInput.max = max || 1;
            if (parseInt(qtyInput.value, 10) > max) {
                qtyInput.value = max || 1;
            }
        }

        function applyBatch(product, batch) {
            batchInput.value = batch.batch_no || '';
            batchIdInput.value = batch.id;
            expiryDisplay.value = batch.expiration_date || '—';
            locationSelect.innerHTML = locationOptionsHtml(batch.locations);
            if (batch.locations.length > 0) {
                qtyInput.value = batch.locations[0].qty;
            }
            syncLocationMax();
        }

        productSearchInput.addEventListener('input', function () {
            const product = findProductByLabel(this.value);
            resetBatch();
            batchDatalist.innerHTML = '';

            if (!product) {
                return;
            }
            product.batches.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.batch_no || '';
                batchDatalist.appendChild(opt);
            });
        });

        batchInput.addEventListener('input', function () {
            const product = findProductByLabel(productSearchInput.value);
            resetBatch();
            if (!product) {
                return;
            }
            const batch = product.batches.find(b => b.batch_no === this.value);
            if (batch) {
                applyBatch(product, batch);
            }
        });

        locationSelect.addEventListener('change', syncLocationMax);

        card.querySelector('.remove-row-btn').addEventListener('click', function () {
            card.remove();
            renumberRows();
        });

        // Exposed so the pre-fill logic below can populate this exact row
        // the same way a manual pick would.
        card._applyBatch = applyBatch;
        card._productSearchInput = productSearchInput;
    }

    document.getElementById('addRowBtn').addEventListener('click', () => addRow());

    // "Generate N Lines" — reuses the exact same addRow() the Add Line
    // button calls, just N times in a row.
    const MAX_GENERATE_LINES = 100;

    function generateLines() {
        const input = document.getElementById('generateLinesInput');
        const requested = parseInt(input.value, 10);

        if (!requested || requested <= 0) {
            return;
        }

        const count = Math.min(requested, MAX_GENERATE_LINES);
        for (let i = 0; i < count; i++) {
            addRow();
        }

        input.value = '';
    }

    document.getElementById('generateLinesBtn').addEventListener('click', generateLines);
    document.getElementById('generateLinesInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            generateLines();
        }
    });

    // Pre-fill from the Product Expiration Report's "Dispose" action — one
    // line per location the batch actually has stock in (a batch split
    // across Warehouse and POS pre-fills two lines, not one).
    if (preselectedBatchId) {
        const match = findBatchById(preselectedBatchId);
        if (match && match.batch.locations.length > 0) {
            match.batch.locations.forEach(loc => {
                addRow();
                const card = document.querySelector('#lineItemsBody .line-item-card:last-child');
                card._productSearchInput.value = productLabel(match.product);
                card._applyBatch(match.product, match.batch);
                card.querySelector('.location-select').value = loc.location_id;
                card.querySelector('.qty-input').value = loc.qty;
            });
        } else {
            addRow();
        }
    } else {
        addRow();
    }
</script>
@endsection
