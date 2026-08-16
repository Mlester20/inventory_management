@extends(Auth::user()->role === 'admin' ? 'layout.app' : 'layout.user')

@section('title', $editingDeliveryReceipt ? 'Edit Draft — ' . $editingDeliveryReceipt->dr_no : 'New Delivery Receipt')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">{{ $editingDeliveryReceipt ? 'Edit Draft — ' . $editingDeliveryReceipt->dr_no : 'New Delivery Receipt' }}</h5>
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

            <form action="{{ $editingDeliveryReceipt ? route('delivery-receipts.update', $editingDeliveryReceipt) : route('delivery-receipts.store') }}" method="POST" id="deliveryReceiptForm">
                @csrf
                @if($editingDeliveryReceipt)
                    @method('PUT')
                @endif
                <input type="hidden" name="transaction_type" id="transaction_type" value="{{ old('transaction_type', $editingDeliveryReceipt?->transaction_type ?? 'advance_order') }}">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="receipt_date" class="form-label">Date</label>
                        <input
                            type="date"
                            name="receipt_date"
                            id="receipt_date"
                            class="form-control @error('receipt_date') is-invalid @enderror"
                            value="{{ old('receipt_date', $editingDeliveryReceipt?->receipt_date?->toDateString() ?? now()->toDateString()) }}"
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
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingDeliveryReceipt?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" name="description" id="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $editingDeliveryReceipt?->description) }}">
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sales Order Number</label>
                        <input type="text" id="so_no_display" class="form-control" readonly placeholder="Blank unless Purchase Order type">
                    </div>
                </div>

                <div class="btn-group mb-4" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="tabBtnAdvance" onclick="showTab('advance_order')">
                        Advance Order
                    </button>
                    <button type="button" class="btn btn-outline-info" id="tabBtnPurchase" onclick="showTab('purchase_order')">
                        Purchase Order
                    </button>
                    <button type="button" class="btn btn-outline-warning" id="tabBtnWalkIn" onclick="showTab('walk_in')">
                        Walk-in
                    </button>
                </div>

                <!-- Advance Order / Walk-in Tab -->
                <div id="advance_order_tab" class="do-tab">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ao_customer_id" class="form-label">Customer</label>
                            <div class="input-group">
                                <select name="customer_id" id="ao_customer_id" class="form-select">
                                    <option value="">-- Select Customer --</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" data-address="{{ $customer->delivery_address }}" {{ old('customer_id', $editingDeliveryReceipt?->customer_id) == $customer->id ? 'selected' : '' }}>{{ $customer->customer_name }}</option>
                                    @endforeach
                                </select>
                                @if(Auth::user()->role === 'admin')
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                        New?
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Delivery Address</label>
                            <textarea id="ao_delivery_address" class="form-control" rows="1" readonly></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Generic Items</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="itemSortSelect" class="form-select form-select-sm" style="width: 160px;">
                                <option value="name_asc">Name (A–Z)</option>
                                <option value="name_desc">Name (Z–A)</option>
                                <option value="code_asc">Code (A–Z)</option>
                            </select>
                            <input type="number" id="generateAoLinesInput" class="form-control form-control-sm" style="width: 90px;" min="1" max="100" placeholder="# lines">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="generateAoLinesBtn">
                                <i class="bx bx-list-plus"></i> Generate
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:3%">#</th>
                                    <th style="width:18%">Generic Description</th>
                                    <th style="width:16%">Item Description</th>
                                    <th style="width:9%">Lot/Batch No.</th>
                                    <th style="width:9%">Expiry Date</th>
                                    <th style="width:14%">Remarks</th>
                                    <th style="width:12%">Qty</th>
                                    <th style="width:8%">Unit</th>
                                    <th style="width:4%"></th>
                                </tr>
                            </thead>
                            <tbody id="aoLineItemsBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Purchase Order Tab -->
                <div id="purchase_order_tab" class="do-tab" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="po_sales_order_id" class="form-label">Sales Order</label>
                            <select name="sales_order_id" id="po_sales_order_id" class="form-select">
                                <option value="">-- Select Sales Order --</option>
                                @foreach ($openSalesOrders as $so)
                                    <option value="{{ $so->id }}" {{ (string) $preselectedSalesOrderId === (string) $so->id ? 'selected' : '' }}>
                                        {{ $so->so_no }} — {{ $so->customer->customer_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer</label>
                            <input type="text" id="po_customer_display" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Delivery Address</label>
                            <textarea id="po_delivery_address" class="form-control" rows="1" readonly></textarea>
                        </div>
                    </div>

                    <h6 class="mb-2">Remaining Generic Lines</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:3%">#</th>
                                    <th style="width:18%">Generic Description</th>
                                    <th style="width:16%">Item Description</th>
                                    <th style="width:9%">Lot/Batch No.</th>
                                    <th style="width:9%">Expiry Date</th>
                                    <th style="width:14%">Remarks</th>
                                    <th style="width:12%">Qty</th>
                                    <th style="width:8%">Unit</th>
                                </tr>
                            </thead>
                            <tbody id="poLineItemsBody"></tbody>
                        </table>
                    </div>
                    <div id="poEmptyMessage" class="alert alert-info d-none">Select a Sales Order to load its remaining lines.</div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" id="addAoRowBtn">
                        <i class="bx bx-plus"></i> Add Generic
                    </button>
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save Delivery Receipt</button>
                    <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="newCustomerError" class="alert alert-danger d-none"></div>
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" id="nc_customer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer Type</label>
                        <input type="text" id="nc_customer_type" class="form-control" placeholder="e.g. Pharmacy, Hospital, Clinic (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price Level</label>
                        <select id="nc_price_level" class="form-select">
                            @foreach (\App\Models\Customer::PRICE_LEVELS as $value => $label)
                                <option value="{{ $value }}" {{ $value === 'retail' ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" id="nc_contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="nc_contact_number" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveNewCustomerBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    let aoRowIndex = 0;
    let poRowIndex = 0;

    // Generic Description is a searchable text field (native <datalist>,
    // matching the same technique already used for the batch-number picker
    // in Inventory Adjustment) rather than a long <select> — the label the
    // user types/picks is matched back to the real generic_name_id.
    const GENERIC_NAMES = @json($genericNamesForJs);

    // A resumed draft's saved lines, split by whether they came from the
    // Advance Order/Walk-in tab (no sales_order_item_id) or the Purchase
    // Order tab (matched by sales_order_item_id once its remaining-items
    // fetch comes back) — mirrors which tab actually submitted them, since
    // the inactive tab's disabled inputs never make it into the request.
    const PREFILL_LINES = @json($prefillLines);
    const AO_PREFILL_LINES = PREFILL_LINES.filter(l => !l.sales_order_item_id);
    const PO_PREFILL_LINES = PREFILL_LINES.filter(l => l.sales_order_item_id);

    function genericLabel(g) {
        return `${g.generic_name} (${g.unit}) — ${g.category_name}`;
    }

    function genericDatalistOptions() {
        return GENERIC_NAMES.map(g => `<option value="${genericLabel(g)}"></option>`).join('');
    }

    function findGenericByLabel(label) {
        return GENERIC_NAMES.find(g => genericLabel(g) === label);
    }

    // "Sort/arrange" toggle — re-sorts the in-memory GENERIC_NAMES array and
    // rebuilds every already-rendered Advance Order row's datalist.
    function sortGenericNames(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(b.generic_name, a.generic_name));
        } else if (mode === 'code_asc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.generic_name, b.generic_name));
        }

        document.querySelectorAll('#aoLineItemsBody datalist').forEach(dl => {
            dl.innerHTML = genericDatalistOptions();
        });
    }

    document.getElementById('itemSortSelect').addEventListener('change', function () {
        sortGenericNames(this.value);
    });

    function showTab(tab) {
        document.getElementById('transaction_type').value = tab;
        // Walk-in reuses the exact same customer + generic-item picker as
        // Advance Order — it's the same mechanics, just a different label
        // for reporting/classification.
        const showAdvancePanel = tab === 'advance_order' || tab === 'walk_in';
        const advanceTabEl = document.getElementById('advance_order_tab');
        const poTabEl = document.getElementById('purchase_order_tab');

        advanceTabEl.style.display = showAdvancePanel ? 'block' : 'none';
        poTabEl.style.display = tab === 'purchase_order' ? 'block' : 'none';

        // Disable (not just hide) the inactive tab's fields immediately.
        // A required-but-merely-hidden field can silently block native
        // HTML5 form validation with no visible error in some browsers —
        // disabling is the reliable way to exempt it and keep it out of
        // the submitted payload.
        advanceTabEl.querySelectorAll('input, select').forEach(el => el.disabled = !showAdvancePanel);
        poTabEl.querySelectorAll('input, select').forEach(el => el.disabled = (tab !== 'purchase_order'));

        document.getElementById('tabBtnAdvance').classList.toggle('active', tab === 'advance_order');
        document.getElementById('tabBtnPurchase').classList.toggle('active', tab === 'purchase_order');
        document.getElementById('tabBtnWalkIn').classList.toggle('active', tab === 'walk_in');

        if (showAdvancePanel) {
            document.getElementById('so_no_display').value = '';
        }
    }

    function itemOptionsHtml(items) {
        if (items.length === 0) {
            return null;
        }
        let html = '<option value="">-- Select Item --</option>';
        items.forEach(item => {
            html += `<option value="${item.id}" data-max="${item.quantity}" data-batch="${item.batch_no || ''}" data-exp="${item.expiration_date || ''}">${item.brand_name} — Batch ${item.batch_no || 'N/A'} (Stock: ${item.quantity}${item.expiration_date ? ', Exp: ' + item.expiration_date : ''})</option>`;
        });
        return html;
    }

    async function fetchAvailableItems(genericNameId) {
        const res = await fetch(`/api/generic-names/${genericNameId}/available-items`);
        return res.json();
    }

    // ---------- Delivery Address auto-fill ----------

    document.getElementById('ao_customer_id').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        document.getElementById('ao_delivery_address').value = selected ? (selected.getAttribute('data-address') || '') : '';
    });

    // ---------- Advance Order / Walk-in tab ----------

    function renumberAoRows() {
        document.querySelectorAll('#aoLineItemsBody tr').forEach((row, i) => {
            row.querySelector('.row-number').textContent = i + 1;
        });
    }

    function addAoRow(prefill = null) {
        const index = aoRowIndex++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="row-number"></td>
            <td>
                <input type="text" class="form-control form-control-sm ao-generic-search-input" list="ao-generic-list-${index}"
                    placeholder="Search generic name..." autocomplete="off" required>
                <datalist id="ao-generic-list-${index}">${genericDatalistOptions()}</datalist>
            </td>
            <td class="item-select-cell"><span class="text-muted small">Select a generic first</span></td>
            <td class="batch-cell"></td>
            <td class="expiry-cell"></td>
            <td class="remarks-cell"></td>
            <td class="qty-cell"></td>
            <td class="unit-cell"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bx bx-trash"></i></button></td>
        `;
        document.getElementById('aoLineItemsBody').appendChild(row);
        renumberAoRows();

        // A row can be created while its tab is hidden (e.g. this initial
        // row is always created, even when a preselected Sales Order shows
        // the Purchase Order tab instead) — match its disabled state to the
        // tab's current visibility so a hidden-but-required field can never
        // silently block native form validation.
        const advanceTabVisible = document.getElementById('advance_order_tab').style.display !== 'none';
        row.querySelectorAll('input, select').forEach(el => el.disabled = !advanceTabVisible);

        row.querySelector('.remove-row-btn').addEventListener('click', () => {
            row.remove();
            renumberAoRows();
        });

        const genericSearchInput = row.querySelector('.ao-generic-search-input');

        async function loadAoAvailability(labelValue, preselect = null) {
            const cells = {
                item: row.querySelector('.item-select-cell'),
                batch: row.querySelector('.batch-cell'),
                expiry: row.querySelector('.expiry-cell'),
                qty: row.querySelector('.qty-cell'),
                remarks: row.querySelector('.remarks-cell'),
                unit: row.querySelector('.unit-cell'),
            };
            cells.batch.innerHTML = '';
            cells.expiry.innerHTML = '';
            cells.qty.innerHTML = '';
            cells.remarks.innerHTML = '';
            cells.unit.innerHTML = '';

            const generic = findGenericByLabel(labelValue);
            if (!generic) {
                cells.item.innerHTML = '<span class="text-muted small">Select a generic first</span>';
                return;
            }
            cells.item.innerHTML = '<span class="text-muted small">Checking availability…</span>';
            const items = await fetchAvailableItems(generic.id);
            renderAvailability(cells, items, index, null, null, generic.unit, preselect);
        }

        genericSearchInput.addEventListener('input', function () {
            loadAoAvailability(this.value);
        });

        // A draft's saved Advance Order/Walk-in line is re-typed straight
        // into the search input to trigger the same availability fetch a
        // manual pick would, with the saved batch/qty/remarks overlaid once
        // it resolves.
        if (prefill) {
            const generic = GENERIC_NAMES.find(g => g.id === prefill.generic_name_id);
            if (generic) {
                genericSearchInput.value = genericLabel(generic);
                loadAoAvailability(genericLabel(generic), prefill);
            }
        }
    }

    function renderAvailability(cells, items, index, salesOrderItemId, remainingQty, unit, preselect = null) {
        const optionsHtml = itemOptionsHtml(items);

        if (!optionsHtml) {
            cells.item.innerHTML = `
                <div class="alert alert-warning py-1 px-2 mb-0 small">
                    <i class="bx bx-error"></i> No stock available — will be skipped.
                </div>
            `;
            return;
        }

        cells.item.innerHTML = `<select class="form-select form-select-sm item-select" name="items[${index}][product_batch_id]">${optionsHtml}</select>`;
        cells.batch.innerHTML = `<input type="text" class="form-control form-control-sm batch-display" readonly>`;
        cells.expiry.innerHTML = `<input type="text" class="form-control form-control-sm expiry-display" readonly>`;
        cells.qty.innerHTML = `<input type="number" class="form-control form-control-sm qty-input" name="items[${index}][qty]" min="1" value="1">`;
        cells.remarks.innerHTML = `<input type="text" class="form-control form-control-sm" name="items[${index}][remarks]" placeholder="Type any text here">`;
        cells.unit.innerHTML = `<input type="text" class="form-control form-control-sm" value="${unit || ''}" readonly>`;

        if (salesOrderItemId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `items[${index}][sales_order_item_id]`;
            hidden.value = salesOrderItemId;
            cells.remarks.appendChild(hidden);
        }

        const itemSelect = cells.item.querySelector('.item-select');
        const qtyInput = cells.qty.querySelector('.qty-input');
        const batchDisplay = cells.batch.querySelector('.batch-display');
        const expiryDisplay = cells.expiry.querySelector('.expiry-display');

        function syncSelected() {
            const selected = itemSelect.options[itemSelect.selectedIndex];
            batchDisplay.value = selected ? (selected.getAttribute('data-batch') || 'N/A') : '';
            expiryDisplay.value = selected ? (selected.getAttribute('data-exp') || 'N/A') : '';

            const maxStock = selected ? parseInt(selected.getAttribute('data-max') || '0', 10) : 0;
            const cap = remainingQty ? Math.min(maxStock, remainingQty) : maxStock;
            qtyInput.max = cap || 1;
            if (parseInt(qtyInput.value, 10) > cap) {
                qtyInput.value = cap || 1;
            }
        }
        itemSelect.addEventListener('change', syncSelected);

        // Overlay a resumed draft's saved batch/qty/remarks for this line —
        // only if that batch is still among the live fetched options (stock
        // may have moved since the draft was saved).
        if (preselect && preselect.product_batch_id) {
            const hasOption = [...itemSelect.options].some(o => o.value === String(preselect.product_batch_id));
            if (hasOption) {
                itemSelect.value = String(preselect.product_batch_id);
            }
        }
        syncSelected();
        if (preselect) {
            if (preselect.qty) qtyInput.value = preselect.qty;
            if (preselect.remarks) {
                const remarksInput = cells.remarks.querySelector('input[type="text"]');
                if (remarksInput) remarksInput.value = preselect.remarks;
            }
        }
    }

    document.getElementById('addAoRowBtn').addEventListener('click', () => addAoRow());

    // "Generate N Lines" — reuses the exact same addAoRow() the Add Generic
    // button calls, just N times in a row, so a batch-generated line is
    // identical to a manually-added one (same indexing, same events bound).
    const MAX_GENERATE_AO_LINES = 100;

    function generateAoLines() {
        const input = document.getElementById('generateAoLinesInput');
        const requested = parseInt(input.value, 10);

        if (!requested || requested <= 0) {
            return;
        }

        const count = Math.min(requested, MAX_GENERATE_AO_LINES);
        for (let i = 0; i < count; i++) {
            addAoRow();
        }

        input.value = '';
    }

    document.getElementById('generateAoLinesBtn').addEventListener('click', generateAoLines);
    document.getElementById('generateAoLinesInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            generateAoLines();
        }
    });

    // ---------- Purchase Order tab ----------

    document.getElementById('po_sales_order_id').addEventListener('change', async function () {
        const soId = this.value;
        const body = document.getElementById('poLineItemsBody');
        const emptyMsg = document.getElementById('poEmptyMessage');
        body.innerHTML = '';
        document.getElementById('po_customer_display').value = '';

        const selectedOption = this.options[this.selectedIndex];
        document.getElementById('so_no_display').value = soId ? selectedOption.textContent.split(' — ')[0].trim() : '';

        if (!soId) {
            emptyMsg.classList.remove('d-none');
            return;
        }

        const res = await fetch(`/api/sales-orders/${soId}/remaining-items`);
        const data = await res.json();

        document.getElementById('po_customer_display').value = data.customer.customer_name;
        document.getElementById('po_delivery_address').value = data.customer.delivery_address || '';

        if (data.items.length === 0) {
            emptyMsg.textContent = 'This Sales Order has no remaining lines to deliver.';
            emptyMsg.classList.remove('d-none');
            return;
        }
        emptyMsg.classList.add('d-none');

        for (const line of data.items) {
            const index = poRowIndex++;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${body.children.length + 1}</td>
                <td>${line.generic_name} <span class="badge bg-warning text-dark">Remaining: ${line.remaining_qty}</span></td>
                <td class="item-select-cell"><span class="text-muted small">Checking availability…</span></td>
                <td class="batch-cell"></td>
                <td class="expiry-cell"></td>
                <td class="remarks-cell"></td>
                <td class="qty-cell"></td>
                <td class="unit-cell"></td>
            `;
            body.appendChild(row);

            const cells = {
                item: row.querySelector('.item-select-cell'),
                batch: row.querySelector('.batch-cell'),
                expiry: row.querySelector('.expiry-cell'),
                qty: row.querySelector('.qty-cell'),
                remarks: row.querySelector('.remarks-cell'),
                unit: row.querySelector('.unit-cell'),
            };
            const items = await fetchAvailableItems(line.generic_name_id);
            // A draft resumed on this tab saved its own batch/qty/remarks
            // per Sales Order line — overlay it onto this freshly fetched
            // pending line (remaining_qty is always live, never stale).
            const preselect = PO_PREFILL_LINES.find(p => String(p.sales_order_item_id) === String(line.sales_order_item_id)) || null;
            renderAvailability(cells, items, index, line.sales_order_item_id, line.remaining_qty, line.unit, preselect);
        }
    });

    // Auto-trigger the Purchase Order tab load if a sales_order_id was preselected via the URL
    // (either from the query string, or from a resumed draft's own header).
    @if($preselectedSalesOrderId)
        showTab('purchase_order');
        document.getElementById('po_sales_order_id').dispatchEvent(new Event('change'));
    @elseif($editingDeliveryReceipt && $editingDeliveryReceipt->transaction_type === 'walk_in')
        showTab('walk_in');
    @endif

    // ---------- New Customer modal ----------

    document.getElementById('saveNewCustomerBtn').addEventListener('click', async function () {
        const errorBox = document.getElementById('newCustomerError');
        errorBox.classList.add('d-none');

        const payload = {
            customer_name: document.getElementById('nc_customer_name').value,
            customer_type: document.getElementById('nc_customer_type').value,
            price_level: document.getElementById('nc_price_level').value,
            vat_type: 'VAT',
            contact_person: document.getElementById('nc_contact_person').value,
            contact_number: document.getElementById('nc_contact_number').value,
            _token: '{{ csrf_token() }}',
        };

        const res = await fetch('{{ route('customers.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const data = await res.json();
            errorBox.textContent = Object.values(data.errors || { error: ['Could not create customer.'] }).flat().join(' ');
            errorBox.classList.remove('d-none');
            return;
        }

        const data = await res.json();
        const select = document.getElementById('ao_customer_id');
        const option = document.createElement('option');
        option.value = data.customer.id;
        option.textContent = data.customer.customer_name;
        option.selected = true;
        select.appendChild(option);

        bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
    });

    // Disable the inactive tab's inputs before submit, so they aren't posted
    document.getElementById('deliveryReceiptForm').addEventListener('submit', function () {
        const activeTab = document.getElementById('transaction_type').value;
        const usesAdvancePanel = activeTab === 'advance_order' || activeTab === 'walk_in';
        const inactiveTabEl = document.getElementById(usesAdvancePanel ? 'purchase_order_tab' : 'advance_order_tab');
        inactiveTabEl.querySelectorAll('input, select').forEach(el => el.disabled = true);
    });

    // A resumed draft's preselected customer needs its delivery address
    // filled the same way the onchange handler does for a manual pick.
    (function () {
        const customerSelect = document.getElementById('ao_customer_id');
        if (customerSelect.value) {
            customerSelect.dispatchEvent(new Event('change'));
        }
    })();

    // Advance Order/Walk-in rows: one per line of a resumed draft, or a
    // single blank row otherwise. A draft resumed on the Purchase Order tab
    // has no Advance Order lines to restore — its rows come from the
    // Sales Order change handler above instead.
    if (AO_PREFILL_LINES.length > 0) {
        AO_PREFILL_LINES.forEach(line => addAoRow(line));
    } else {
        addAoRow();
    }
</script>
@endsection
