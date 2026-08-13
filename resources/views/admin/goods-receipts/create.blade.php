@extends('layout.app')

@section('title', 'New Goods Receipt')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Goods Receipt</h5>
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

            <form action="{{ route('goods-receipts.store') }}" method="POST" id="goodsReceiptForm">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="receipt_date" class="form-label">Date</label>
                        <input
                            type="date"
                            name="receipt_date"
                            id="receipt_date"
                            class="form-control @error('receipt_date') is-invalid @enderror"
                            value="{{ old('receipt_date', now()->toDateString()) }}"
                            required
                        >
                        @error('receipt_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
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

                <div class="btn-group mb-4" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="tabBtnDirect" onclick="showTab('direct')">
                        Direct Receipt
                    </button>
                    <button type="button" class="btn btn-outline-info" id="tabBtnPo" onclick="showTab('purchase_order')">
                        Against Purchase Order
                    </button>
                </div>

                <!-- Direct Receipt Tab -->
                <div id="direct_tab" class="gr-tab">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="direct_supplier_id" class="form-label">Supplier</label>
                            <select name="supplier_id" id="direct_supplier_id" class="form-select">
                                <option value="">-- Select Supplier --</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Received Items</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="itemSortSelect" class="form-select form-select-sm" style="width: 160px;">
                                <option value="name_asc">Name (A–Z)</option>
                                <option value="name_desc">Name (Z–A)</option>
                                <option value="code_asc">Code (A–Z)</option>
                            </select>
                            <input type="number" id="generateDirectLinesInput" class="form-control form-control-sm" style="width: 90px;" min="1" max="100" placeholder="# lines">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="generateDirectLinesBtn">
                                <i class="bx bx-list-plus"></i> Generate
                            </button>
                        </div>
                    </div>
                    <div id="directLineItemsBody"></div>
                </div>

                <!-- Against Purchase Order Tab -->
                <div id="purchase_order_tab" class="gr-tab" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_order_id" class="form-label">Purchase Order</label>
                            <select name="purchase_order_id" id="purchase_order_id" class="form-select">
                                <option value="">-- Select Purchase Order --</option>
                                @foreach ($openPurchaseOrders as $po)
                                    <option value="{{ $po->id }}" {{ (string) $preselectedPurchaseOrderId === (string) $po->id ? 'selected' : '' }}>
                                        {{ $po->po_no }} — {{ $po->supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" id="po_supplier_display" class="form-control" readonly>
                        </div>
                    </div>

                    <h6 class="mb-2">Pending Lines</h6>
                    <div id="poLineItemsBody"></div>
                    <div id="poEmptyMessage" class="alert alert-info d-none">Select a Purchase Order to load its pending lines.</div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" id="addDirectRowBtn">
                        <i class="bx bx-plus"></i> Add Item
                    </button>
                    <button type="submit" class="btn btn-primary">Save Goods Receipt</button>
                    <a href="{{ route('goods-receipts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const ITEMS = @json($itemsForJs);

    let directRowIndex = 0;
    let poRowIndex = 0;

    function showTab(tab) {
        const directVisible = tab === 'direct';
        document.getElementById('direct_tab').style.display = directVisible ? 'block' : 'none';
        document.getElementById('purchase_order_tab').style.display = directVisible ? 'none' : 'block';
        document.getElementById('tabBtnDirect').classList.toggle('active', directVisible);
        document.getElementById('tabBtnPo').classList.toggle('active', !directVisible);

        // Disable (not just hide) the inactive tab's fields so hidden `required`
        // inputs can't silently block native HTML5 validation on submit.
        document.querySelectorAll('#direct_tab input, #direct_tab select').forEach(el => el.disabled = !directVisible);
        document.querySelectorAll('#purchase_order_tab input, #purchase_order_tab select').forEach(el => el.disabled = directVisible);
    }

    // ---------- Direct Receipt tab ----------

    function currentDirectSupplierId() {
        return document.getElementById('direct_supplier_id').value;
    }

    // Item is a searchable text field (native <datalist>, matching the same
    // technique already used for Generic Description pickers) instead of a
    // long <select> — the label the user types/picks is matched back to the
    // real product_id, still scoped to the currently chosen supplier.
    function itemLabel(item) {
        return item.name;
    }

    function itemsForSupplier(supplierId) {
        return supplierId ? ITEMS.filter(i => String(i.supplier_id) === String(supplierId)) : ITEMS;
    }

    function itemDatalistOptions() {
        return itemsForSupplier(currentDirectSupplierId()).map(i => `<option value="${itemLabel(i)}"></option>`).join('');
    }

    function findItemByLabel(label) {
        return itemsForSupplier(currentDirectSupplierId()).find(i => itemLabel(i) === label);
    }

    // "Sort/arrange" toggle — re-sorts the shared ITEMS array (also used by
    // the Against-PO tab's Brand suggestions) and rebuilds every
    // already-rendered Direct Receipt row's datalist.
    function sortItems(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            ITEMS.sort((a, b) => collator.compare(b.name, a.name));
        } else if (mode === 'code_asc') {
            ITEMS.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            ITEMS.sort((a, b) => collator.compare(a.name, b.name));
        }

        document.querySelectorAll('#directLineItemsBody datalist').forEach(dl => {
            dl.innerHTML = itemDatalistOptions();
        });
    }

    document.getElementById('itemSortSelect').addEventListener('change', function () {
        sortItems(this.value);
    });

    function addDirectRow() {
        const index = directRowIndex++;
        const card = document.createElement('div');
        card.className = 'line-item-card border rounded p-3 mb-3';
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted">Item</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Item</label>
                    <input type="text" class="form-control item-search-input" list="direct-item-list-${index}"
                        placeholder="Search item..." autocomplete="off" required>
                    <datalist id="direct-item-list-${index}">${itemDatalistOptions()}</datalist>
                    <input type="hidden" name="items[${index}][product_id]" class="item-id-input">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Unit Cost</label>
                    <input type="number" name="items[${index}][unit_cost]" class="form-control cost-input" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Batch No.</label>
                    <input type="text" name="items[${index}][batch_no]" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Expiry</label>
                    <input type="date" name="items[${index}][expiration_date]" class="form-control">
                </div>
            </div>
        `;
        document.getElementById('directLineItemsBody').appendChild(card);

        const itemSearchInput = card.querySelector('.item-search-input');
        const itemIdInput = card.querySelector('.item-id-input');
        const costInput = card.querySelector('.cost-input');
        itemSearchInput.addEventListener('input', function () {
            const item = findItemByLabel(this.value);
            itemIdInput.value = item ? item.id : '';
            if (item) {
                costInput.value = item.unit_cost.toFixed(2);
            }
        });

        card.querySelector('.remove-row-btn').addEventListener('click', () => card.remove());

        // A row can be added while the Direct Receipt tab is inactive (e.g. a
        // purchase_order_id preselected via the URL already switched tabs
        // before this initial row gets created) — keep it disabled in that case.
        const directVisible = document.getElementById('direct_tab').style.display !== 'none';
        card.querySelectorAll('input, select').forEach(el => el.disabled = !directVisible);
    }

    document.getElementById('addDirectRowBtn').addEventListener('click', addDirectRow);

    // "Generate N Lines" — reuses the exact same addDirectRow() the Add Item
    // button calls, just N times in a row, so a batch-generated line is
    // identical to a manually-added one (same indexing, same events bound).
    // Direct Receipt only — the Against Purchase Order tab's rows come from
    // the PO's own pending lines, not manually added one at a time.
    const MAX_GENERATE_DIRECT_LINES = 100;

    function generateDirectLines() {
        const input = document.getElementById('generateDirectLinesInput');
        const requested = parseInt(input.value, 10);

        if (!requested || requested <= 0) {
            return;
        }

        const count = Math.min(requested, MAX_GENERATE_DIRECT_LINES);
        for (let i = 0; i < count; i++) {
            addDirectRow();
        }

        input.value = '';
    }

    document.getElementById('generateDirectLinesBtn').addEventListener('click', generateDirectLines);
    document.getElementById('generateDirectLinesInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            generateDirectLines();
        }
    });

    // Refresh each row's item datalist when the supplier changes, since items
    // are filtered to that supplier's catalog. A row's current selection is
    // cleared if it no longer belongs to the newly chosen supplier.
    document.getElementById('direct_supplier_id').addEventListener('change', function () {
        document.querySelectorAll('#directLineItemsBody .line-item-card').forEach(card => {
            const itemSearchInput = card.querySelector('.item-search-input');
            const itemIdInput = card.querySelector('.item-id-input');
            const datalist = card.querySelector('datalist');
            datalist.innerHTML = itemDatalistOptions();

            if (itemIdInput.value && !findItemByLabel(itemSearchInput.value)) {
                itemSearchInput.value = '';
                itemIdInput.value = '';
            }
        });
    });

    // ---------- Against Purchase Order tab ----------

    document.getElementById('purchase_order_id').addEventListener('change', async function () {
        const poId = this.value;
        const body = document.getElementById('poLineItemsBody');
        const emptyMsg = document.getElementById('poEmptyMessage');
        body.innerHTML = '';
        document.getElementById('po_supplier_display').value = '';

        if (!poId) {
            emptyMsg.classList.remove('d-none');
            return;
        }

        const res = await fetch(`/api/purchase-orders/${poId}/pending-items`);
        const data = await res.json();

        document.getElementById('po_supplier_display').value = data.supplier.supplier_name;

        if (data.items.length === 0) {
            emptyMsg.textContent = 'This Purchase Order has no pending lines to receive.';
            emptyMsg.classList.remove('d-none');
            return;
        }
        emptyMsg.classList.add('d-none');

        data.items.forEach(line => {
            const index = poRowIndex++;
            const card = document.createElement('div');
            card.className = 'line-item-card border rounded p-3 mb-3';

            // Brand siblings: other Products under the same Generic Item as
            // what the PO line specified — lets the receiver record the
            // brand actually delivered, since the supplier may not send the
            // exact same brand the PO was raised against.
            const brandSiblings = ITEMS.filter(i => i.generic_name_id != null && i.generic_name_id === line.generic_name_id);

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">${line.item_name}</span>
                    <span class="badge bg-warning text-dark">Remaining: ${line.remaining_qty}</span>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Brand</label>
                        <input type="text" class="form-control po-brand-input" list="po-brand-list-${index}"
                            value="${line.description}" autocomplete="off">
                        <datalist id="po-brand-list-${index}">${brandSiblings.map(i => `<option value="${i.name}"></option>`).join('')}</datalist>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Qty</label>
                        <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" max="${line.remaining_qty}" value="${line.remaining_qty}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Unit Cost</label>
                        <input type="number" name="items[${index}][unit_cost]" class="form-control po-cost-input" step="0.01" min="0" value="${parseFloat(line.unit_cost).toFixed(2)}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Batch No.</label>
                        <input type="text" name="items[${index}][batch_no]" class="form-control">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Expiry</label>
                        <input type="date" name="items[${index}][expiration_date]" class="form-control">
                    </div>
                </div>
                <input type="hidden" name="items[${index}][product_id]" value="${line.product_id}" class="po-product-id-input">
                <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${line.purchase_order_item_id}">
            `;
            body.appendChild(card);

            const qtyInput = card.querySelector('.qty-input');
            qtyInput.addEventListener('input', function () {
                if (parseInt(this.value, 10) > line.remaining_qty) {
                    this.value = line.remaining_qty;
                }
            });

            const brandInput = card.querySelector('.po-brand-input');
            const productIdInput = card.querySelector('.po-product-id-input');
            const costInput = card.querySelector('.po-cost-input');
            brandInput.addEventListener('input', function () {
                // Only re-match against this generic's siblings — a
                // half-typed or unmatched brand leaves product_id untouched
                // at its last valid value rather than clearing it, since
                // this field always starts pre-filled with a valid brand.
                const match = brandSiblings.find(i => i.name === this.value);
                if (match) {
                    productIdInput.value = match.id;
                    costInput.value = match.unit_cost.toFixed(2);
                }
            });

            const poVisible = document.getElementById('purchase_order_tab').style.display !== 'none';
            card.querySelectorAll('input, select').forEach(el => el.disabled = !poVisible);
        });
    });

    // Auto-trigger the Purchase Order tab load if a purchase_order_id was preselected via the URL
    @if($preselectedPurchaseOrderId)
        showTab('purchase_order');
        document.getElementById('purchase_order_id').dispatchEvent(new Event('change'));
    @else
        showTab('direct');
    @endif

    // Start with one empty Direct Receipt row (self-disables if the Direct tab isn't active)
    addDirectRow();
</script>
@endsection