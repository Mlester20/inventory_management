@extends('layout.app')

@section('title', $editingAdjustment ? 'Edit Draft — ' . $editingAdjustment->adjustment_no : 'New Inventory Adjustment')

@section('content')
    <style>
        /* The suggestion dropdown overlaps whatever sits below the search field
           (it's position:absolute, so it doesn't push that content down) — it
           must paint fully opaque or the row underneath bleeds through. Not
           relying on Bootstrap's .list-group defaults here since this
           template's own theme CSS doesn't reliably set them. */
        .item-suggestions {
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: .375rem;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
        }
        .item-suggestions button {
            display: block;
            width: 100%;
            text-align: left;
            background-color: #fff;
            border: 0;
            border-bottom: 1px solid #f1f1f1;
            padding: .375rem .75rem;
            cursor: pointer;
        }
        .item-suggestions button:last-child { border-bottom: 0; }
        .item-suggestions button:hover { background-color: #f8f9fa; }
    </style>
    <div class="card mt-3">
        <h5 class="card-header">{{ $editingAdjustment ? 'Edit Draft — ' . $editingAdjustment->adjustment_no : 'New Inventory Adjustment' }}</h5>
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

            <form action="{{ $editingAdjustment ? route('inventory-adjustments.update', $editingAdjustment) : route('inventory-adjustments.store') }}" method="POST" id="adjustmentForm">
                @csrf
                @if($editingAdjustment)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="adjustment_date" class="form-control" value="{{ old('adjustment_date', $editingAdjustment?->adjustment_date?->toDateString() ?? now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            @foreach (\App\Models\InventoryAdjustment::TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('adjustment_type', $editingAdjustment?->adjustment_type ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Prepared By</label>
                        <select name="prepared_by" class="form-select">
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingAdjustment?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $editingAdjustment?->description ?? '') }}">
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Line Items</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="itemSortSelect" class="form-select form-select-sm" style="width: 160px;">
                            <option value="name_asc">Name (A–Z)</option>
                            <option value="name_desc">Name (Z–A)</option>
                            <option value="code_asc">Code (A–Z)</option>
                        </select>
                        <input type="number" id="generateLinesInput" class="form-control form-control-sm" style="width: 90px;" min="1" max="100" placeholder="# lines">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="generateLinesBtn">
                            <i class="bx bx-list-plus"></i> Generate
                        </button>
                    </div>
                </div>

                <div id="lineItemsBody"></div>

                <div class="mb-3 mt-3">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="2">{{ old('note', $editingAdjustment?->note ?? '') }}</textarea>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Line
                    </button>
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save</button>
                    <a href="{{ route('inventory-adjustments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const PRODUCTS = @json($productsForJs);
    const LOCATIONS = @json($locations->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'is_default' => $l->is_default]));

    const preselectedProductId = @json($preselectedProductId);
    const prefillLines = @json($prefillLines);
    let rowIndex = 0;

    // Item Description is a searchable text field with its own JS-built
    // suggestion dropdown (see renderSuggestions below) instead of a long
    // <select> or a native <datalist> — the label the user types/picks is
    // matched back to the real product_id. A native <datalist> was tried
    // first, but Chrome's own suggestion popup clips long item names to its
    // own internal width no matter how wide the <input> is, so the dropdown
    // had to be built by hand to actually show the full name.
    function productLabel(p) {
        return p.name;
    }

    function findProductByLabel(label) {
        return PRODUCTS.find(p => productLabel(p) === label);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Up to 30 matches as clickable rows, or the first 30 entries when the
    // query is empty (clicking into a blank field browses, same as
    // <datalist> used to list everything on focus).
    function renderSuggestions(box, items, query, labelFn, idFn) {
        const q = query.trim().toLowerCase();
        const matches = (q ? items.filter(i => labelFn(i).toLowerCase().includes(q)) : items).slice(0, 30);
        if (matches.length === 0) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        box.innerHTML = matches.map(i =>
            `<button type="button" class="small" data-id="${idFn(i)}">${escapeHtml(labelFn(i))}</button>`
        ).join('');
        box.style.display = 'block';
    }

    // Only batches with no expiry or a still-valid expiry are offered as
    // suggestions — an expired lot shouldn't be the easy default pick, but
    // typing one that's already on the product (expired or not) still
    // auto-fills its expiry via the batch-input listener below.
    function activeBatches(product) {
        const today = new Date().toISOString().slice(0, 10);
        return product.batches.filter(b => !b.expiration_date || b.expiration_date >= today);
    }

    // "Sort/arrange" toggle — re-sorts the in-memory PRODUCTS array; the
    // next time any row's suggestion dropdown opens, it reads straight from
    // this same array, so there's nothing else to rebuild here.
    function sortProducts(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            PRODUCTS.sort((a, b) => collator.compare(b.name, a.name));
        } else if (mode === 'code_asc') {
            PRODUCTS.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            PRODUCTS.sort((a, b) => collator.compare(a.name, b.name));
        }
    }

    document.getElementById('itemSortSelect').addEventListener('change', function () {
        sortProducts(this.value);
    });

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Line #' + (i + 1);
        });
    }

    function addRow(prefill = {}) {
        const { productId = '', batchNo = '', locationId = '', qty = '', remarks = '' } = prefill;
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
                <div class="col-12 position-relative">
                    <label class="form-label small mb-1">Item Description</label>
                    <input type="text" class="form-control product-search-input"
                        placeholder="Search item..." autocomplete="off" required>
                    <input type="hidden" name="lines[${index}][product_id]" class="product-id-input">
                    <div class="item-suggestions product-suggestions position-absolute w-100"
                        style="z-index: 1050; max-height: 280px; overflow-y: auto; display: none;"></div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-2">
                    <label class="form-label small mb-1">Lot/Batch No.</label>
                    <input type="text" name="lines[${index}][batch_no]" class="form-control batch-input" list="batch-list-${index}">
                    <datalist id="batch-list-${index}" class="batch-datalist"></datalist>
                    <input type="hidden" name="lines[${index}][product_batch_id]" class="batch-id-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Expiry Date</label>
                    <input type="date" name="lines[${index}][expiration_date]" class="form-control expiry-input">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Location</label>
                    <select name="lines[${index}][location_id]" class="form-select">
                        ${LOCATIONS.map(loc => `<option value="${loc.id}" ${loc.is_default ? 'selected' : ''}>${loc.name}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="lines[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Unit</label>
                    <input type="text" class="form-control unit-display" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Remarks</label>
                    <input type="text" name="lines[${index}][remarks]" class="form-control">
                </div>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(card);
        bindRowEvents(card);
        renumberRows();

        if (productId) {
            const product = PRODUCTS.find(p => String(p.id) === String(productId));
            if (product) {
                card.querySelector('.product-search-input').value = productLabel(product);
                card.querySelector('.product-search-input').dispatchEvent(new Event('input'));
            }
        }

        if (batchNo) {
            const batchInput = card.querySelector('.batch-input');
            batchInput.value = batchNo;
            batchInput.dispatchEvent(new Event('input'));
        }

        if (locationId) {
            card.querySelector('select[name$="[location_id]"]').value = locationId;
        }

        if (qty) {
            card.querySelector('.qty-input').value = qty;
        }

        if (remarks) {
            card.querySelector('input[name$="[remarks]"]').value = remarks;
        }
    }

    function bindRowEvents(card) {
        const productSearchInput = card.querySelector('.product-search-input');
        const productSuggestions = card.querySelector('.product-suggestions');
        const productIdInput = card.querySelector('.product-id-input');
        const unitDisplay = card.querySelector('.unit-display');
        const batchInput = card.querySelector('.batch-input');
        const batchIdInput = card.querySelector('.batch-id-input');
        const expiryInput = card.querySelector('.expiry-input');
        const batchDatalist = card.querySelector('.batch-datalist');

        function applyProduct(product) {
            productIdInput.value = product ? product.id : '';
            unitDisplay.value = product ? product.unit : '';
            batchDatalist.innerHTML = '';
            batchIdInput.value = '';

            if (product) {
                activeBatches(product).forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.batch_no || '';
                    batchDatalist.appendChild(opt);
                });
            }
        }

        productSearchInput.addEventListener('input', function () {
            // A resumed draft's prefill dispatches this same 'input' event
            // programmatically (see addRow's prefill handling) without
            // focusing the field first — only paint the dropdown for an
            // actual, focused keystroke.
            if (document.activeElement === this) {
                renderSuggestions(productSuggestions, PRODUCTS, this.value, productLabel, p => p.id);
            }
            applyProduct(findProductByLabel(this.value));
        });

        productSearchInput.addEventListener('focus', function () {
            renderSuggestions(productSuggestions, PRODUCTS, this.value, productLabel, p => p.id);
        });

        productSearchInput.addEventListener('blur', function () {
            setTimeout(() => { productSuggestions.style.display = 'none'; }, 150);
        });

        productSuggestions.addEventListener('mousedown', function (e) {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            e.preventDefault();
            const product = PRODUCTS.find(p => String(p.id) === btn.dataset.id);
            if (!product) return;
            productSearchInput.value = productLabel(product);
            applyProduct(product);
            productSuggestions.style.display = 'none';
            productSuggestions.innerHTML = '';
        });

        batchInput.addEventListener('input', function () {
            const product = findProductByLabel(productSearchInput.value);
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

    // "Generate N Lines" — reuses the exact same addRow() the Add Line
    // button calls, just N times in a row, so a batch-generated line is
    // identical to a manually-added one (same indexing, same events bound).
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

    // Start with one row pre-selecting the product (launched from the
    // Lot/Serial & Expiry tab's "Adjust Inventory" button), or one row per
    // line of the original adjustment (launched from a Write-off redirect),
    // or a single blank row otherwise.
    if (prefillLines.length > 0) {
        prefillLines.forEach(line => {
            addRow({ productId: line.product_id, batchNo: line.batch_no, locationId: line.location_id, qty: line.qty, remarks: line.remarks });
        });
    } else {
        addRow({ productId: preselectedProductId });
    }
</script>
@endsection
