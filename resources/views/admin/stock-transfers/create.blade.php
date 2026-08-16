@extends('layout.app')

@section('title', $editingStockTransfer ? 'Edit Draft — ' . $editingStockTransfer->reference : 'New Stock Transfer')

@section('content')
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
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width:3%">#</th>
                                <th style="width:24%">Generic Description</th>
                                <th style="width:24%">Item / Lot</th>
                                <th style="width:14%">Available</th>
                                <th style="width:14%">Qty</th>
                                <th style="width:8%">Unit</th>
                                <th style="width:4%"></th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody"></tbody>
                    </table>
                </div>

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

    function genericLabel(g) {
        return `${g.generic_name} (${g.unit}) — ${g.category_name}`;
    }

    function genericDatalistOptions() {
        return GENERIC_NAMES.map(g => `<option value="${genericLabel(g)}"></option>`).join('');
    }

    function findGenericByLabel(label) {
        return GENERIC_NAMES.find(g => genericLabel(g) === label);
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
        document.querySelectorAll('#lineItemsBody tr').forEach((row, i) => {
            row.querySelector('.row-number').textContent = i + 1;
        });
    }

    function addRow(prefill = {}) {
        const { genericLabel: prefillGenericLabel = '', productBatchId = '', qty = '' } = prefill;
        const index = rowIndex++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="row-number"></td>
            <td>
                <input type="text" class="form-control form-control-sm generic-search-input" list="generic-list-${index}"
                    placeholder="Search generic name..." autocomplete="off" required>
                <datalist id="generic-list-${index}">${genericDatalistOptions()}</datalist>
            </td>
            <td class="item-select-cell"><span class="text-muted small">Select a generic first</span></td>
            <td class="available-cell"></td>
            <td class="qty-cell"></td>
            <td class="unit-cell"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bx bx-trash"></i></button></td>
        `;
        document.getElementById('lineItemsBody').appendChild(row);
        renumberRows();

        row.querySelector('.remove-row-btn').addEventListener('click', () => {
            row.remove();
            renumberRows();
        });

        const genericSearchInput = row.querySelector('.generic-search-input');
        genericSearchInput.addEventListener('input', async function () {
            await loadItemsForRow(row, this.value);
        });

        row.dataset.genericLabel = '';

        // A draft's saved lines are re-typed straight into the search input
        // (triggering the same async item fetch a manual pick would), then
        // the matching batch/qty are preselected once the options render.
        if (prefillGenericLabel) {
            genericSearchInput.value = prefillGenericLabel;
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
        const index = [...document.querySelectorAll('#lineItemsBody tr')].indexOf(row);
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
        document.querySelectorAll('#lineItemsBody tr').forEach(row => {
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
