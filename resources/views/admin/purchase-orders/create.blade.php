@extends('layout.app')

@section('title', $editingPurchaseOrder ? 'Edit Draft — ' . $editingPurchaseOrder->po_no : 'New Purchase Order')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">{{ $editingPurchaseOrder ? 'Edit Draft — ' . $editingPurchaseOrder->po_no : 'New Purchase Order' }}</h5>
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

            <form action="{{ $editingPurchaseOrder ? route('purchase-orders.update', $editingPurchaseOrder) : route('purchase-orders.store') }}" method="POST" id="purchaseOrderForm">
                @csrf
                @if($editingPurchaseOrder)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="supplier_search" class="form-label">Supplier</label>
                        <input
                            type="text"
                            name="supplier_search"
                            id="supplier_search"
                            class="form-control @error('supplier_id') is-invalid @enderror"
                            list="supplier_datalist"
                            placeholder="Search supplier..."
                            autocomplete="off"
                            value="{{ old('supplier_search', $editingPurchaseOrder?->supplier?->supplier_name) }}"
                            required
                        >
                        <datalist id="supplier_datalist">
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_name }}"></option>
                            @endforeach
                        </datalist>
                        <input type="hidden" name="supplier_id" id="supplier_id" value="{{ old('supplier_id', $editingPurchaseOrder?->supplier_id) }}">
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
                            value="{{ old('order_date', $editingPurchaseOrder?->order_date?->toDateString() ?? now()->toDateString()) }}"
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
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingPurchaseOrder?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>
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
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="bx bx-plus"></i> Add Item
                    </button>
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save Purchase Order</button>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const GENERIC_NAMES = @json($genericNamesForJs);
    const prefillLines = @json($prefillLines);

    const SUPPLIERS = @json($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->supplier_name])->values());

    function findSupplierByName(name) {
        return SUPPLIERS.find(s => s.name === name) || null;
    }

    let rowIndex = 0;

    // Generic Description is a searchable text field with its own JS-built
    // suggestion dropdown (see renderItemSuggestions below) rather than a
    // long <select> or a native <datalist> — the label the user types/picks
    // is matched back to the real generic_name_id. A PO orders a Generic
    // Item; the specific brand isn't chosen until Goods Receipt time (same
    // as the existing Brand field). A native <datalist> was tried first,
    // but Chrome's own suggestion popup clips long generic names to its own
    // internal width no matter how wide the <input> is, so the dropdown had
    // to be built by hand to actually show the full name.
    function itemLabel(g) {
        return `${g.generic_name} (${g.unit}) — ${g.category_name}`;
    }

    function findItemByLabel(label) {
        return GENERIC_NAMES.find(g => itemLabel(g) === label);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Renders up to 30 matches as clickable rows under the Generic
    // Description input. Capped so a one-character query against a large
    // catalog doesn't paint hundreds of rows.
    function renderItemSuggestions(box, items, query) {
        const q = query.trim().toLowerCase();
        if (!q) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        const matches = items.filter(i => itemLabel(i).toLowerCase().includes(q)).slice(0, 30);
        if (matches.length === 0) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        box.innerHTML = matches.map(i =>
            `<button type="button" class="list-group-item list-group-item-action text-start small py-1" data-item-id="${i.id}">${escapeHtml(itemLabel(i))}</button>`
        ).join('');
        box.style.display = 'block';
    }

    // "Sort/arrange" toggle — re-sorts the shared GENERIC_NAMES array; the
    // next time any row's suggestion dropdown opens, it reads straight from
    // this same array, so there's nothing else to rebuild here.
    function sortItems(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(b.generic_name, a.generic_name));
        } else if (mode === 'code_asc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.generic_name, b.generic_name));
        }
    }

    document.getElementById('itemSortSelect').addEventListener('change', function () {
        sortItems(this.value);
    });

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Item #' + (i + 1);
        });
    }

    function addRow(prefill = {}) {
        const { genericLabel = '', qty = '', unit = '', unitCost = null, remarks = '' } = prefill;
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
                <div class="col-12 position-relative">
                    <label class="form-label small mb-1">Generic Description</label>
                    <input type="text" class="form-control item-search-input"
                        placeholder="Search generic name..." autocomplete="off" required>
                    <input type="hidden" name="items[${index}][generic_name_id]" class="item-id-input">
                    <div class="list-group item-suggestions position-absolute w-100 shadow-sm"
                        style="z-index: 1050; max-height: 280px; overflow-y: auto; display: none;"></div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Unit Cost</label>
                    <input type="number" name="items[${index}][unit_cost]" class="form-control cost-input" step="0.01" min="0" value="0" required>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Unit</label>
                    <input type="text" name="items[${index}][unit]" class="form-control unit-input" readonly>
                </div>
                <div class="col-md-9">
                    <label class="form-label small mb-1">Remarks</label>
                    <input type="text" name="items[${index}][remarks]" class="form-control">
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

        // A draft's saved line is re-typed straight into the search input
        // to resolve generic_name_id (same as a manual pick), then
        // qty/cost/remarks are overlaid — everything here is already
        // client-side in GENERIC_NAMES, no fetch needed. Unit is not
        // separately restored here — the dispatchEvent() above already
        // derived it from the resolved item, which is this field's only
        // source of truth (read-only, see the input's own definition above).
        if (genericLabel) {
            const itemSearchInput = card.querySelector('.item-search-input');
            itemSearchInput.value = genericLabel;
            itemSearchInput.dispatchEvent(new Event('input'));
            if (qty) card.querySelector('.qty-input').value = qty;
            if (unitCost !== null && unitCost !== undefined) card.querySelector('.cost-input').value = Number(unitCost).toFixed(2);
            if (remarks) card.querySelector('input[name$="[remarks]"]').value = remarks;
        }

        computeTotals();
    }

    function bindRowEvents(card) {
        const itemSearchInput = card.querySelector('.item-search-input');
        const suggestionsBox = card.querySelector('.item-suggestions');
        const itemIdInput = card.querySelector('.item-id-input');
        const costInput = card.querySelector('.cost-input');
        const unitInput = card.querySelector('.unit-input');

        function applyItem(item) {
            itemIdInput.value = item ? item.id : '';
            // No unit_cost auto-fill — a Generic Item has no single cost of
            // its own (different brands under it can cost differently), so
            // the user enters it manually. Unit is read-only and always
            // mirrors whatever's currently selected, instead of only
            // filling in when blank (which could leave it out of sync
            // after picking a different item).
            unitInput.value = item ? (item.unit || '') : '';
            computeTotals();
        }

        itemSearchInput.addEventListener('input', function () {
            // A resumed draft's prefill dispatches this same 'input' event
            // programmatically (see addRow above) without focusing the field
            // first — only paint the dropdown for an actual, focused keystroke.
            if (document.activeElement === this) {
                renderItemSuggestions(suggestionsBox, GENERIC_NAMES, this.value);
            }
            applyItem(findItemByLabel(this.value));
        });

        itemSearchInput.addEventListener('focus', function () {
            if (this.value.trim()) {
                renderItemSuggestions(suggestionsBox, GENERIC_NAMES, this.value);
            }
        });

        // A short delay so a click on a suggestion (below) registers before
        // the dropdown is hidden out from under it.
        itemSearchInput.addEventListener('blur', function () {
            setTimeout(() => { suggestionsBox.style.display = 'none'; }, 150);
        });

        // mousedown (not click) fires before the input's blur, so the pick
        // lands reliably without racing the blur handler above.
        suggestionsBox.addEventListener('mousedown', function (e) {
            const row = e.target.closest('[data-item-id]');
            if (!row) {
                return;
            }
            e.preventDefault();
            const item = GENERIC_NAMES.find(g => String(g.id) === row.dataset.itemId);
            if (!item) {
                return;
            }
            itemSearchInput.value = itemLabel(item);
            applyItem(item);
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
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

    // "Generate N Lines" — reuses the exact same addRow() the Add Item
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

    document.getElementById('supplier_search').addEventListener('input', function () {
        const match = findSupplierByName(this.value);
        const hidden = document.getElementById('supplier_id');
        hidden.value = match ? match.id : '';
        hidden.dispatchEvent(new Event('change'));
    });

    // Kept for future use if per-supplier filtering is ever reintroduced —
    // currently a no-op refresh, since the item list no longer depends on
    // the chosen supplier.
    document.getElementById('supplier_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(card => {
            const itemSearchInput = card.querySelector('.item-search-input');
            const itemIdInput = card.querySelector('.item-id-input');

            if (itemIdInput.value && !findItemByLabel(itemSearchInput.value)) {
                itemSearchInput.value = '';
                itemIdInput.value = '';
            }
        });
    });

    // Start with one row per line of a resumed draft, or a single blank
    // row for a brand new order.
    if (prefillLines.length > 0) {
        prefillLines.forEach(line => {
            addRow({
                genericLabel: line.generic_label,
                qty: line.qty,
                unit: line.unit,
                unitCost: line.unit_cost,
                remarks: line.remarks,
            });
        });
    } else {
        addRow();
    }
</script>
@endsection
