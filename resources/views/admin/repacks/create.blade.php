@extends('layout.app')

@section('title', 'New Repack')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Repack</h5>
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

            <p class="text-muted small">
                Converts stock of one product into stock of another at the same location (e.g. breaking a BX
                into loose PC). The destination lot carries the source lot's own Batch No/Expiry by default —
                override them only if this repack should land in a different lot.
            </p>

            <form action="{{ route('repacks.store') }}" method="POST" id="repackForm">
                @csrf

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="location_id" class="form-label">Location</label>
                        <select name="location_id" id="location_id" class="form-select" required>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id', $locations->firstWhere('is_default', true)?->id) == $location->id ? 'selected' : '' }}>
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
                                <option value="{{ $user->id }}" {{ old('prepared_by', auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="remarks" class="form-label">Remarks <span class="text-muted">(optional)</span></label>
                        <input type="text" name="remarks" id="remarks" class="form-control" value="{{ old('remarks') }}">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Lines</h6>
                    <button type="button" class="btn btn-sm btn-primary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Line
                    </button>
                </div>
                <div id="lineItemsBody"></div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Repack</button>
                    <a href="{{ route('repacks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const GENERIC_NAMES = @json($genericNamesForJs);
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

    function currentLocationId() {
        return document.getElementById('location_id').value;
    }

    async function fetchAvailableItems(genericNameId) {
        const res = await fetch(`/api/generic-names/${genericNameId}/available-items?location_id=${currentLocationId()}`);
        return res.json();
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Line #' + (i + 1);
        });
    }

    function addRow() {
        const index = rowIndex++;
        const row = document.createElement('div');
        row.className = 'line-item-card border rounded p-3 mb-3';
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted line-item-number">Line</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>

            <div class="border rounded p-2 mb-2 bg-light-subtle">
                <div class="fw-semibold small text-muted mb-2">FROM (Source — deducted)</div>
                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label small mb-1">Generic Description</label>
                        <input type="text" class="form-control form-control-sm source-generic-input" list="source-generic-list-${index}"
                            placeholder="Search generic name..." autocomplete="off" required>
                        <datalist id="source-generic-list-${index}">${genericDatalistOptions()}</datalist>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-12">
                        <label class="form-label small mb-1">Lot / Batch</label>
                        <div class="source-item-select-cell"><span class="text-muted small">Select a generic first</span></div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">Available</label>
                        <div class="source-available-cell"></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">Qty to Take</label>
                        <div class="source-qty-cell"></div>
                    </div>
                </div>
            </div>

            <div class="border rounded p-2 bg-light-subtle">
                <div class="fw-semibold small text-muted mb-2">TO (Destination — produced)</div>
                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label small mb-1">Generic Description</label>
                        <input type="text" class="form-control form-control-sm destination-generic-input" list="destination-generic-list-${index}"
                            placeholder="Search generic name..." autocomplete="off" required>
                        <datalist id="destination-generic-list-${index}">${genericDatalistOptions()}</datalist>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Item</label>
                        <div class="destination-item-select-cell"><span class="text-muted small">Select a generic first</span></div>
                        <input type="hidden" name="lines[${index}][destination_product_id]" class="destination-product-id-input">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Qty Produced</label>
                        <input type="number" class="form-control form-control-sm" name="lines[${index}][destination_qty]" min="1" value="1" required>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Destination Batch No <span class="text-muted">(optional — defaults to the source lot's own)</span></label>
                        <input type="text" class="form-control form-control-sm" name="lines[${index}][destination_batch_no]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Destination Expiry <span class="text-muted">(optional — defaults to the source lot's own)</span></label>
                        <input type="date" class="form-control form-control-sm" name="lines[${index}][destination_expiration_date]">
                    </div>
                </div>
            </div>
        `;
        document.getElementById('lineItemsBody').appendChild(row);
        renumberRows();

        row.querySelector('.remove-row-btn').addEventListener('click', () => {
            row.remove();
            renumberRows();
        });

        bindSourcePicker(row, index);
        bindDestinationPicker(row, index);
    }

    function bindSourcePicker(row, index) {
        const genericInput = row.querySelector('.source-generic-input');
        const itemCell = row.querySelector('.source-item-select-cell');
        const availableCell = row.querySelector('.source-available-cell');
        const qtyCell = row.querySelector('.source-qty-cell');

        genericInput.addEventListener('input', async function () {
            this.title = this.value;
            const generic = findGenericByLabel(this.value);
            availableCell.innerHTML = '';
            qtyCell.innerHTML = '';
            if (!generic) {
                itemCell.innerHTML = '<span class="text-muted small">Select a generic first</span>';
                return;
            }
            itemCell.innerHTML = '<span class="text-muted small">Checking availability…</span>';
            const items = await fetchAvailableItems(generic.id);
            if (items.length === 0) {
                itemCell.innerHTML = '<div class="alert alert-warning py-1 px-2 mb-0 small"><i class="bx bx-error"></i> No stock available at this location.</div>';
                return;
            }
            let optionsHtml = '<option value="">-- Select Lot --</option>';
            items.forEach(item => {
                optionsHtml += `<option value="${item.id}" data-max="${item.quantity}">${item.brand_name || item.item_name} — Batch ${item.batch_no || 'N/A'} (Available: ${item.quantity}${item.expiration_date ? ', Exp: ' + item.expiration_date : ''})</option>`;
            });
            itemCell.innerHTML = `<select class="form-select form-select-sm source-item-select" name="lines[${index}][source_batch_id]" required>${optionsHtml}</select>`;
            availableCell.innerHTML = '<span class="source-available-display">—</span>';
            qtyCell.innerHTML = `<input type="number" class="form-control form-control-sm source-qty-input" name="lines[${index}][source_qty]" min="1" value="1" required>`;

            const itemSelect = itemCell.querySelector('.source-item-select');
            const qtyInput = qtyCell.querySelector('.source-qty-input');
            const availableDisplay = availableCell.querySelector('.source-available-display');

            itemSelect.addEventListener('change', function () {
                const selected = itemSelect.options[itemSelect.selectedIndex];
                const maxStock = selected ? parseInt(selected.getAttribute('data-max') || '0', 10) : 0;
                availableDisplay.textContent = selected && selected.value ? maxStock : '—';
                qtyInput.max = maxStock || 1;
                if (parseInt(qtyInput.value, 10) > maxStock) {
                    qtyInput.value = maxStock || 1;
                }
            });
        });
    }

    function bindDestinationPicker(row, index) {
        const genericInput = row.querySelector('.destination-generic-input');
        const itemCell = row.querySelector('.destination-item-select-cell');
        const productIdInput = row.querySelector('.destination-product-id-input');

        genericInput.addEventListener('input', function () {
            this.title = this.value;
            const generic = findGenericByLabel(this.value);
            productIdInput.value = '';
            if (!generic || !generic.products || generic.products.length === 0) {
                itemCell.innerHTML = '<span class="text-muted small">No item under this generic yet</span>';
                return;
            }
            let optionsHtml = '<option value="">-- Select Item --</option>';
            generic.products.forEach(p => {
                optionsHtml += `<option value="${p.id}">${p.label}</option>`;
            });
            itemCell.innerHTML = `<select class="form-select form-select-sm destination-item-select">${optionsHtml}</select>`;
            itemCell.querySelector('.destination-item-select').addEventListener('change', function () {
                productIdInput.value = this.value;
            });
        });
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    // Lots/items are scoped to the chosen Location — changing it invalidates
    // every already-picked source row, since a batch available at Warehouse
    // may not exist at all at POS.
    document.getElementById('location_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .source-generic-input').forEach(input => {
            if (input.value) {
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    addRow();
</script>
@endsection
