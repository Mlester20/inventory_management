@extends('layout.app')

@section('title', $editingStockTransfer ? 'Edit Draft — ' . $editingStockTransfer->reference : 'New Stock Transfer')

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
        <h5 class="card-header">{{ $editingStockTransfer ? 'Edit Draft — ' . $editingStockTransfer->reference : 'New Stock Transfer' }}</h5>
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

            <form action="{{ $editingStockTransfer ? route('stock-transfers.update', $editingStockTransfer) : route('stock-transfers.store') }}" method="POST" id="stockTransferForm">
                @csrf
                @if($editingStockTransfer)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ old('date', $editingStockTransfer?->date?->toDateString() ?? now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="from_location_id" class="form-label">From Location</label>
                        <select name="from_location_id" id="from_location_id" class="form-select" required>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ old('from_location_id', $editingStockTransfer?->from_location_id ?? $locations->firstWhere('is_default', true)?->id) == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="to_location_id" class="form-label">To Location</label>
                        <select name="to_location_id" id="to_location_id" class="form-select" required>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ old('to_location_id', $editingStockTransfer?->to_location_id ?? $locations->firstWhere('is_default', false)?->id) == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="prepared_by" class="form-label">Prepared By</label>
                        <select name="prepared_by" id="prepared_by" class="form-select">
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingStockTransfer?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="sameLocationWarning" class="alert alert-warning d-none">From and To locations must be different.</div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Items to Transfer</h6>
                    <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Generic
                    </button>
                </div>
                <div id="lineItemsBody"></div>

                <div class="mt-3">
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save Stock Transfer</button>
                    <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const GENERIC_NAMES = @json($genericNamesForJs);
    const PREFILL_LINES = @json($prefillLines);
    let rowIndex = 0;

    // Generic Description is a searchable text field with its own JS-built
    // suggestion dropdown (see renderSuggestions below) rather than a long
    // <select> or a native <datalist> — the label the user types/picks is
    // matched back to the real generic_name_id. A native <datalist> was
    // tried first, but Chrome's own suggestion popup clips long generic
    // names to its own internal width no matter how wide the <input> is, so
    // the dropdown had to be built by hand to actually show the full name.
    function genericLabel(g) {
        return `${g.generic_name} (${g.unit}) — ${g.category_name}`;
    }

    function findGenericByLabel(label) {
        return GENERIC_NAMES.find(g => genericLabel(g) === label);
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

    function currentFromLocationId() {
        return document.getElementById('from_location_id').value;
    }

    async function fetchAvailableItems(genericNameId) {
        const res = await fetch(`/api/generic-names/${genericNameId}/available-items?location_id=${currentFromLocationId()}`);
        return res.json();
    }

    function itemOptionsHtml(items) {
        if (items.length === 0) {
            return null;
        }
        let html = '<option value="">-- Select Item --</option>';
        items.forEach(item => {
            html += `<option value="${item.id}" data-max="${item.quantity}">${item.brand_name} — Batch ${item.batch_no || 'N/A'} (Available: ${item.quantity}${item.expiration_date ? ', Exp: ' + item.expiration_date : ''})</option>`;
        });
        return html;
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Item #' + (i + 1);
        });
    }

    function addRow(prefill = {}) {
        const { genericLabel: prefillGenericLabel = '', productBatchId = '', qty = '' } = prefill;
        const index = rowIndex++;
        const row = document.createElement('div');
        row.className = 'line-item-card border rounded p-3 mb-3';
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted line-item-number">Item</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>

            <div class="row g-2">
                <div class="col-md-12 position-relative">
                    <label class="form-label small mb-1">Generic Description</label>
                    <input type="text" class="form-control form-control-sm generic-search-input"
                        placeholder="Search generic name..." autocomplete="off" required>
                    <div class="item-suggestions generic-suggestions position-absolute w-100"
                        style="z-index: 1050; max-height: 280px; overflow-y: auto; display: none;"></div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-12">
                    <label class="form-label small mb-1">Item / Lot</label>
                    <div class="item-select-cell"><span class="text-muted small">Select a generic first</span></div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-6 col-md-4">
                    <label class="form-label small mb-1">Available</label>
                    <div class="available-cell"></div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small mb-1">Qty</label>
                    <div class="qty-cell"></div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small mb-1">Unit</label>
                    <div class="unit-cell"></div>
                </div>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(row);
        renumberRows();

        row.querySelector('.remove-row-btn').addEventListener('click', () => {
            row.remove();
            renumberRows();
        });

        const genericSearchInput = row.querySelector('.generic-search-input');
        const genericSuggestions = row.querySelector('.generic-suggestions');

        genericSearchInput.addEventListener('input', async function () {
            this.title = this.value;
            // A resumed draft's prefill sets the value and calls
            // loadItemsForRow() directly (see below) without focusing the
            // field first — only paint the dropdown for an actual, focused
            // keystroke.
            if (document.activeElement === this) {
                renderSuggestions(genericSuggestions, GENERIC_NAMES, this.value, genericLabel, g => g.id);
            }
            await loadItemsForRow(row, this.value);
        });

        genericSearchInput.addEventListener('focus', function () {
            renderSuggestions(genericSuggestions, GENERIC_NAMES, this.value, genericLabel, g => g.id);
        });

        genericSearchInput.addEventListener('blur', function () {
            setTimeout(() => { genericSuggestions.style.display = 'none'; }, 150);
        });

        genericSuggestions.addEventListener('mousedown', function (e) {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            e.preventDefault();
            const generic = GENERIC_NAMES.find(g => String(g.id) === btn.dataset.id);
            if (!generic) return;
            genericSearchInput.value = genericLabel(generic);
            genericSearchInput.title = genericLabel(generic);
            loadItemsForRow(row, genericLabel(generic));
            genericSuggestions.style.display = 'none';
            genericSuggestions.innerHTML = '';
        });

        row.dataset.genericLabel = '';

        // A draft's saved lines are re-typed straight into the search input
        // (triggering the same async item fetch a manual pick would), then
        // the matching batch/qty are preselected once the options render.
        if (prefillGenericLabel) {
            genericSearchInput.value = prefillGenericLabel;
            genericSearchInput.title = prefillGenericLabel;
            loadItemsForRow(row, prefillGenericLabel, { productBatchId, qty });
        }
    }

    async function loadItemsForRow(row, genericLabelValue, preselect = null) {
        const cells = {
            item: row.querySelector('.item-select-cell'),
            available: row.querySelector('.available-cell'),
            qty: row.querySelector('.qty-cell'),
            unit: row.querySelector('.unit-cell'),
        };
        cells.available.innerHTML = '';
        cells.qty.innerHTML = '';
        cells.unit.innerHTML = '';

        const generic = findGenericByLabel(genericLabelValue);
        if (!generic) {
            cells.item.innerHTML = '<span class="text-muted small">Select a generic first</span>';
            return;
        }
        row.dataset.genericLabel = genericLabelValue;
        cells.item.innerHTML = '<span class="text-muted small">Checking availability…</span>';
        const items = await fetchAvailableItems(generic.id);
        renderAvailability(row, cells, items, generic.unit, preselect);
    }

    function renderAvailability(row, cells, items, unit, preselect = null) {
        const index = [...document.querySelectorAll('#lineItemsBody .line-item-card')].indexOf(row);
        const optionsHtml = itemOptionsHtml(items);

        if (!optionsHtml) {
            cells.item.innerHTML = `
                <div class="alert alert-warning py-1 px-2 mb-0 small">
                    <i class="bx bx-error"></i> No stock available at this location.
                </div>
            `;
            return;
        }

        cells.item.innerHTML = `<select class="form-select form-select-sm item-select" name="lines[${index}][product_batch_id]">${optionsHtml}</select>`;
        cells.available.innerHTML = `<span class="available-display">—</span>`;
        cells.qty.innerHTML = `<input type="number" class="form-control form-control-sm qty-input" name="lines[${index}][qty]" min="1" value="1">`;
        cells.unit.innerHTML = `<input type="text" class="form-control form-control-sm" value="${unit || ''}" readonly>`;

        const itemSelect = cells.item.querySelector('.item-select');
        const qtyInput = cells.qty.querySelector('.qty-input');
        const availableDisplay = cells.available.querySelector('.available-display');

        function syncSelected() {
            const selected = itemSelect.options[itemSelect.selectedIndex];
            itemSelect.title = selected ? selected.textContent : '';
            const maxStock = selected ? parseInt(selected.getAttribute('data-max') || '0', 10) : 0;
            availableDisplay.textContent = selected ? maxStock : '—';
            qtyInput.max = maxStock || 1;
            if (parseInt(qtyInput.value, 10) > maxStock) {
                qtyInput.value = maxStock || 1;
            }
        }
        itemSelect.addEventListener('change', syncSelected);

        if (preselect && preselect.productBatchId) {
            itemSelect.value = String(preselect.productBatchId);
        }
        syncSelected();

        if (preselect && preselect.qty) {
            qtyInput.value = preselect.qty;
        }
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    // Names/qty available are scoped to the From Location — when it
    // changes, every already-picked row needs its availability refetched
    // (a batch available at Warehouse may not exist at all at POS, etc.).
    document.getElementById('from_location_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(row => {
            if (row.dataset.genericLabel) {
                loadItemsForRow(row, row.dataset.genericLabel);
            }
        });
    });

    function checkSameLocation() {
        const from = document.getElementById('from_location_id').value;
        const to = document.getElementById('to_location_id').value;
        const warning = document.getElementById('sameLocationWarning');
        const same = from && to && from === to;
        warning.classList.toggle('d-none', !same);
        return !same;
    }

    document.getElementById('from_location_id').addEventListener('change', checkSameLocation);
    document.getElementById('to_location_id').addEventListener('change', checkSameLocation);

    document.getElementById('stockTransferForm').addEventListener('submit', function (e) {
        if (!checkSameLocation()) {
            e.preventDefault();
        }
    });

    // Start with one row per line of the draft being resumed, or a single
    // blank row for a brand new transfer.
    if (PREFILL_LINES.length > 0) {
        PREFILL_LINES.forEach(line => {
            addRow({ genericLabel: line.generic_label, productBatchId: line.product_batch_id, qty: line.qty });
        });
    } else {
        addRow();
    }
</script>
@endsection
