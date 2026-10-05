@extends('layout.app')

@section('title', $editingRepack ? 'Edit Draft — ' . $editingRepack->reference : 'New Repack')

@section('content')
    <style>
        /* The suggestion dropdown overlaps whatever sits below the Generic
           Description field (it's position:absolute, so it doesn't push that
           content down) — it must paint fully opaque or the row underneath
           bleeds through. Not relying on Bootstrap's .list-group defaults
           here since this template's own theme CSS doesn't reliably set them. */
        .generic-suggestions {
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: .375rem;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
        }
        .generic-suggestions button {
            display: block;
            width: 100%;
            text-align: left;
            background-color: #fff;
            border: 0;
            border-bottom: 1px solid #f1f1f1;
            padding: .375rem .75rem;
            cursor: pointer;
        }
        .generic-suggestions button:last-child { border-bottom: 0; }
        .generic-suggestions button:hover { background-color: #f8f9fa; }
    </style>
    <div class="card mt-3">
        <h5 class="card-header">{{ $editingRepack ? 'Edit Draft — ' . $editingRepack->reference : 'New Repack' }}</h5>
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
                into loose PC). Search the Generic Item once per line — it fills in the FROM (what's being
                broken down) and TO (what it becomes) sides below, since a repack always stays within the
                same medicine. The destination lot carries the source lot's own Batch No/Expiry by default —
                override them only if this repack should land in a different lot. Once a destination item is
                picked, its Price/Cost is suggested as the source's divided evenly across the pieces produced
                (e.g. ₱100/BX into 100 tabs = ₱1/tab) — editable before posting, and only applied to the item
                if left checked.
                If a repack was a mistake, it can be voided from its page while every piece it produced is still
                there; once some are sold or moved, correct the quantity with an Inventory Adjustment instead.
            </p>

            <form action="{{ $editingRepack ? route('repacks.update', $editingRepack) : route('repacks.store') }}" method="POST" id="repackForm">
                @csrf
                @if($editingRepack)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ old('date', $editingRepack?->date?->toDateString() ?? now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="location_id" class="form-label">Location</label>
                        <select name="location_id" id="location_id" class="form-select" required>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id', $editingRepack?->location_id ?? $locations->firstWhere('is_default', true)?->id) == $location->id ? 'selected' : '' }}>
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
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingRepack?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="remarks" class="form-label">Remarks <span class="text-muted">(optional)</span></label>
                        <input type="text" name="remarks" id="remarks" class="form-control" value="{{ old('remarks', $editingRepack?->remarks) }}">
                    </div>
                </div>

                <h6 class="mb-2">Lines</h6>
                <div id="lineItemsBody"></div>

                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Line
                    </button>
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save Repack</button>
                    <a href="{{ route('repacks.index') }}" class="btn btn-outline-secondary">Cancel</a>
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

    function findGenericByLabel(label) {
        return GENERIC_NAMES.find(g => genericLabel(g) === label);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Own JS-built suggestion dropdown rather than a native <datalist> — Chrome's
    // own suggestion popup clips long generic names to its own internal width no
    // matter how wide the <input> is, so the dropdown had to be built by hand to
    // actually show the full name. An empty query browses the first 30 entries
    // instead of showing nothing, matching how <datalist> listed everything on focus.
    function renderGenericSuggestions(box, query) {
        const q = query.trim().toLowerCase();
        const matches = (q ? GENERIC_NAMES.filter(g => genericLabel(g).toLowerCase().includes(q)) : GENERIC_NAMES).slice(0, 30);
        if (matches.length === 0) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        box.innerHTML = matches.map(g =>
            `<button type="button" class="small" data-generic-id="${g.id}">${escapeHtml(genericLabel(g))}</button>`
        ).join('');
        box.style.display = 'block';
    }

    // A BX and its PC aren't always the same Generic Item row — the established convention (see
    // ProductsCatalogSheetImport) is that the same generic_name text can have a separate row per
    // Unit ("SAMPLE REPACK ITEM" BX and "SAMPLE REPACK ITEM" PC are two different generic_names,
    // same name, different unit), each with its own product(s). So the destination list is every
    // product under every generic that shares this generic_name TEXT, not just this one exact row.
    function productsSharingGenericName(genericNameText) {
        return GENERIC_NAMES
            .filter(g => g.generic_name.toLowerCase() === genericNameText.toLowerCase())
            .flatMap(g => g.products || []);
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

    function addRow(prefill = null) {
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

            <div class="mb-2 position-relative">
                <label class="form-label small mb-1">Generic Description</label>
                <input type="text" class="form-control form-control-sm generic-input"
                    placeholder="Search generic name..." autocomplete="off" required>
                <div class="generic-suggestions position-absolute w-100"
                    style="z-index: 1050; max-height: 280px; overflow-y: auto; display: none;"></div>
                <div class="form-text">Picked once — fills in both sides below, since a repack is always within the same medicine.</div>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                <div class="border rounded p-2 bg-light-subtle h-100">
                    <div class="fw-semibold small text-muted mb-2">FROM (Source — deducted)</div>
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label small mb-1">Lot / Batch</label>
                            <div class="source-item-select-cell"><span class="text-muted small">Search a generic first</span></div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6 col-md-6">
                            <label class="form-label small mb-1">Available</label>
                            <div class="source-available-cell"></div>
                        </div>
                        <div class="col-6 col-md-6">
                            <label class="form-label small mb-1">Qty to Take</label>
                            <div class="source-qty-cell"></div>
                        </div>
                    </div>
                </div>
                </div>

                <div class="col-md-6">
                <div class="border rounded p-2 bg-light-subtle h-100">
                    <div class="fw-semibold small text-muted mb-2">TO (Destination — produced)</div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Item</label>
                            <div class="destination-item-select-cell"><span class="text-muted small">Search a generic first</span></div>
                            <input type="hidden" name="lines[${index}][destination_product_id]" class="destination-product-id-input">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Qty Produced</label>
                            <input type="number" class="form-control form-control-sm destination-qty-input" name="lines[${index}][destination_qty]" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Batch No <span class="text-muted">(optional — defaults to the source lot's own)</span></label>
                            <input type="text" class="form-control form-control-sm" name="lines[${index}][destination_batch_no]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Expiry <span class="text-muted">(optional — defaults to the source lot's own)</span></label>
                            <input type="date" class="form-control form-control-sm" name="lines[${index}][destination_expiration_date]">
                        </div>
                    </div>
                    <div class="border rounded p-2 mt-2 bg-white price-suggestion-box" style="display:none;">
                        <div class="small text-muted mb-2">
                            Suggested Price/Cost — the source's, divided evenly across the pieces this line produces
                            (e.g. ₱100/BX into 100 tabs = ₱1/tab), per Sir.
                        </div>
                        <div class="row g-2">
                            <div class="col-6 col-md-6">
                                <label class="form-label small mb-1">New Price</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm destination-price-input" name="lines[${index}][destination_price]">
                            </div>
                            <div class="col-6 col-md-6">
                                <label class="form-label small mb-1">New Cost</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm destination-cost-input" name="lines[${index}][destination_cost]">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input apply-price-input" name="lines[${index}][apply_price]" value="1" checked>
                                    <label class="form-check-label small">Update this item's Price/Cost when this Repack is posted <span class="text-muted current-price-display"></span></label>
                                </div>
                            </div>
                        </div>
                    </div>
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

        const picker = bindGenericPicker(row, index);

        row.querySelector('.destination-qty-input').addEventListener('input', () => recomputeSuggestion(row));
        row.querySelector('.destination-price-input').addEventListener('input', function () { this.dataset.priceDirty = '1'; });
        row.querySelector('.destination-cost-input').addEventListener('input', function () { this.dataset.priceDirty = '1'; });

        // A resumed draft's saved line is re-applied here: the generic search is
        // set and resolved (same async chain a manual pick goes through, so the
        // FROM/TO selects populate the same way), then the saved qty/batch/
        // expiry/price/cost/apply are overlaid once that settles.
        if (prefill && prefill.generic_label) {
            const genericInput = row.querySelector('.generic-input');
            genericInput.value = prefill.generic_label;
            genericInput.title = prefill.generic_label;
            const generic = findGenericByLabel(prefill.generic_label);

            if (generic) {
                picker.applyGeneric(generic).then(() => {
                    const setValue = (selector, value) => {
                        if (value !== null && value !== undefined && value !== '') {
                            row.querySelector(selector).value = value;
                        }
                    };

                    const sourceSelect = row.querySelector('.source-item-select');
                    if (sourceSelect && prefill.source_batch_id) {
                        sourceSelect.value = prefill.source_batch_id;
                        sourceSelect.dispatchEvent(new Event('change'));
                    }
                    setValue('.source-qty-input', prefill.source_qty);

                    const destSelect = row.querySelector('.destination-item-select');
                    if (destSelect && prefill.destination_product_id) {
                        destSelect.value = prefill.destination_product_id;
                        destSelect.dispatchEvent(new Event('change'));
                    }
                    setValue('.destination-qty-input', prefill.destination_qty);
                    setValue('[name$="[destination_batch_no]"]', prefill.destination_batch_no);
                    setValue('[name$="[destination_expiration_date]"]', prefill.destination_expiration_date);

                    const priceInput = row.querySelector('.destination-price-input');
                    const costInput = row.querySelector('.destination-cost-input');
                    if (prefill.destination_price !== null && prefill.destination_price !== undefined) {
                        priceInput.value = prefill.destination_price;
                        priceInput.dataset.priceDirty = '1';
                    }
                    if (prefill.destination_cost !== null && prefill.destination_cost !== undefined) {
                        costInput.value = prefill.destination_cost;
                        costInput.dataset.priceDirty = '1';
                    }
                    row.querySelector('.apply-price-input').checked = !!prefill.apply_price;
                });
            }
        }
    }

    // Per Sir: suggested destination Price/Cost = source's Price/Cost, divided evenly across
    // however many destination units this line produces. Only overwrites the New Price/New Cost
    // inputs while the encoder hasn't typed into them directly (priceDirty), same rule as the
    // Withholding Tax suggestion elsewhere in the app.
    function recomputeSuggestion(row) {
        const box = row.querySelector('.price-suggestion-box');
        const sourceQty = parseFloat(row.querySelector('.source-qty-input')?.value || '0');
        const destQty = parseFloat(row.querySelector('.destination-qty-input')?.value || '0');
        const sourcePrice = parseFloat(row.dataset.sourceUnitPrice || '');
        const sourceCost = parseFloat(row.dataset.sourceUnitCost || '');
        const destProductId = row.querySelector('.destination-product-id-input')?.value;

        if (!destProductId || !sourceQty || !destQty || isNaN(sourcePrice)) {
            box.style.display = 'none';
            return;
        }

        box.style.display = '';
        const suggestedPrice = Math.round((sourcePrice * sourceQty / destQty) * 100) / 100;
        const suggestedCost = isNaN(sourceCost) ? null : Math.round((sourceCost * sourceQty / destQty) * 100) / 100;

        const priceInput = box.querySelector('.destination-price-input');
        const costInput = box.querySelector('.destination-cost-input');
        if (priceInput.dataset.priceDirty !== '1') priceInput.value = suggestedPrice;
        if (costInput.dataset.priceDirty !== '1' && suggestedCost !== null) costInput.value = suggestedCost;

        const currentPrice = row.dataset.destUnitPrice;
        box.querySelector('.current-price-display').textContent = currentPrice !== undefined
            ? `(current Price: ₱${parseFloat(currentPrice).toFixed(2)})` : '';
    }

    // One generic search fills in BOTH sides — a repack is always within the same medicine, just a
    // different packaging. FROM only lists lots that actually have stock, under the exact generic
    // that was searched, at the chosen Location. TO lists every product under every generic that
    // shares that same generic_name TEXT (a BX and its PC are sometimes the same generic row,
    // sometimes two rows that only differ by Unit — see productsSharingGenericName()), minus
    // whichever lot is currently picked as the source, since the destination must be a different
    // product. Repacking creates NEW stock at the destination, so no stock check there.
    function bindGenericPicker(row, index) {
        const genericInput = row.querySelector('.generic-input');
        const suggestionsBox = row.querySelector('.generic-suggestions');
        const sourceItemCell = row.querySelector('.source-item-select-cell');
        const availableCell = row.querySelector('.source-available-cell');
        const qtyCell = row.querySelector('.source-qty-cell');
        const destItemCell = row.querySelector('.destination-item-select-cell');
        const productIdInput = row.querySelector('.destination-product-id-input');

        function renderDestinationOptions(generic, excludeProductId) {
            productIdInput.value = '';
            row.querySelector('.price-suggestion-box').style.display = 'none';
            const products = productsSharingGenericName(generic.generic_name)
                .filter(p => String(p.id) !== String(excludeProductId || ''));
            if (products.length === 0) {
                destItemCell.innerHTML = '<span class="text-muted small">No other item under this generic yet — add one under Products first</span>';
                return;
            }
            let optionsHtml = '<option value="">-- Select Item --</option>';
            products.forEach(p => {
                optionsHtml += `<option value="${p.id}" data-price="${p.unit_price}" data-cost="${p.unit_cost}">${p.label}${p.unit ? ' (' + p.unit + ')' : ''}</option>`;
            });
            destItemCell.innerHTML = `<select class="form-select form-select-sm destination-item-select">${optionsHtml}</select>`;
            destItemCell.querySelector('.destination-item-select').addEventListener('change', function () {
                productIdInput.value = this.value;
                const selected = this.options[this.selectedIndex];
                row.dataset.destUnitPrice = selected?.getAttribute('data-price') ?? '';
                row.dataset.destUnitCost = selected?.getAttribute('data-cost') ?? '';
                // A newly picked item gets a fresh suggestion rather than one left over from
                // whatever was typed for the previous item.
                const priceInput = row.querySelector('.destination-price-input');
                const costInput = row.querySelector('.destination-cost-input');
                delete priceInput.dataset.priceDirty;
                delete costInput.dataset.priceDirty;
                recomputeSuggestion(row);
            });
        }

        async function applyGeneric(generic) {
            availableCell.innerHTML = '';
            qtyCell.innerHTML = '';
            if (!generic) {
                sourceItemCell.innerHTML = '<span class="text-muted small">Search and pick a generic above</span>';
                destItemCell.innerHTML = '<span class="text-muted small">Search and pick a generic above</span>';
                productIdInput.value = '';
                return;
            }

            renderDestinationOptions(generic, null);

            sourceItemCell.innerHTML = '<span class="text-muted small">Checking availability…</span>';
            const items = await fetchAvailableItems(generic.id);
            if (items.length === 0) {
                sourceItemCell.innerHTML = '<div class="alert alert-warning py-1 px-2 mb-0 small"><i class="bx bx-error"></i> No stock available at this location.</div>';
                return;
            }
            let optionsHtml = '<option value="">-- Select Lot --</option>';
            items.forEach(item => {
                optionsHtml += `<option value="${item.id}" data-max="${item.quantity}" data-price="${item.unit_price}" data-cost="${item.unit_cost}" data-product-id="${item.product_id}">${item.brand_name || item.item_name} — Batch ${item.batch_no || 'N/A'} (Available: ${item.quantity}${item.expiration_date ? ', Exp: ' + item.expiration_date : ''})</option>`;
            });
            sourceItemCell.innerHTML = `<select class="form-select form-select-sm source-item-select" name="lines[${index}][source_batch_id]" required>${optionsHtml}</select>`;
            availableCell.innerHTML = '<span class="source-available-display">—</span>';
            qtyCell.innerHTML = `<input type="number" class="form-control form-control-sm source-qty-input" name="lines[${index}][source_qty]" min="1" value="1" required>`;

            const itemSelect = sourceItemCell.querySelector('.source-item-select');
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
                row.dataset.sourceUnitPrice = selected?.getAttribute('data-price') ?? '';
                row.dataset.sourceUnitCost = selected?.getAttribute('data-cost') ?? '';
                // The picked lot's own product can't also be the destination — re-render TO
                // without it, so the destination select never even offers the same item.
                renderDestinationOptions(generic, selected?.getAttribute('data-product-id'));
                recomputeSuggestion(row);
            });
            qtyInput.addEventListener('input', () => recomputeSuggestion(row));
        }

        genericInput.addEventListener('input', function () {
            this.title = this.value;
            // A resumed draft's prefill dispatches this same 'input' event
            // programmatically (see addRow's prefill handling) without
            // focusing the field first — only paint the dropdown for an
            // actual, focused keystroke.
            if (document.activeElement === this) {
                renderGenericSuggestions(suggestionsBox, this.value);
            }
            applyGeneric(findGenericByLabel(this.value));
        });

        genericInput.addEventListener('focus', function () {
            renderGenericSuggestions(suggestionsBox, this.value);
        });

        // A short delay so a click on a suggestion (below) registers before
        // the dropdown is hidden out from under it.
        genericInput.addEventListener('blur', function () {
            setTimeout(() => { suggestionsBox.style.display = 'none'; }, 150);
        });

        // mousedown (not click) fires before the input's blur, so the pick
        // lands reliably without racing the blur handler above.
        suggestionsBox.addEventListener('mousedown', function (e) {
            const btn = e.target.closest('[data-generic-id]');
            if (!btn) {
                return;
            }
            e.preventDefault();
            const generic = GENERIC_NAMES.find(g => String(g.id) === btn.dataset.genericId);
            if (!generic) {
                return;
            }
            genericInput.value = genericLabel(generic);
            genericInput.title = genericInput.value;
            applyGeneric(generic);
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
        });

        return { applyGeneric };
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    // Lots/items are scoped to the chosen Location — changing it invalidates
    // every already-picked source row, since a batch available at Warehouse
    // may not exist at all at POS.
    document.getElementById('location_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .generic-input').forEach(input => {
            if (input.value) {
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    // One row per saved line of the draft being resumed, or a single blank
    // row for a brand new Repack.
    if (PREFILL_LINES.length > 0) {
        PREFILL_LINES.forEach(line => addRow(line));
    } else {
        addRow();
    }
</script>
@endsection
