@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', $editingInvoice ? 'Edit Draft — ' . $editingInvoice->sales_no : 'New Invoice')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">{{ $editingInvoice ? 'Edit Draft — ' . $editingInvoice->sales_no : 'New Sales Invoice' }}</h5>
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

            <form action="{{ $editingInvoice ? route('invoices.update', $editingInvoice) : route('invoices.store') }}" method="POST" id="invoiceForm">
                @csrf
                @if($editingInvoice)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="customer_name" class="form-label">Customer Name</label>
                        <input
                            type="text"
                            name="customer_name"
                            id="customer_name"
                            list="customerNamesList"
                            autocomplete="off"
                            class="form-control @error('customer_name') is-invalid @enderror"
                            value="{{ old('customer_name', $editingInvoice?->customer_name) }}"
                            required
                        >
                        <datalist id="customerNamesList">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->customer_name }}"></option>
                            @endforeach
                        </datalist>
                        <input type="hidden" name="customer_id" id="customer_id">
                        @error('customer_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="po_no" class="form-label">P.O. No.</label>
                        <input
                            type="text"
                            name="po_no"
                            id="po_no"
                            class="form-control @error('po_no') is-invalid @enderror"
                            value="{{ old('po_no', $editingInvoice?->po_no) }}"
                        >
                        @error('po_no')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="sales_no" class="form-label">Sales No.</label>
                        <input
                            type="text"
                            id="sales_no"
                            class="form-control"
                            value="{{ $salesNo }}"
                            readonly
                        >
                        <div class="form-text">Assigned automatically when the invoice is saved.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="osca_no" class="form-label">Senior Citizen / PWD ID No.</label>
                        <input
                            type="text"
                            name="osca_no"
                            id="osca_no"
                            class="form-control @error('osca_no') is-invalid @enderror"
                            value="{{ old('osca_no', $editingInvoice?->osca_no) }}"
                            placeholder="Leave blank if not applicable"
                        >
                        @error('osca_no')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">If filled in, a 20% SC/PWD discount is applied to VATable sales.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="less_wt" class="form-label">Withholding Tax (optional)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="less_wt"
                            id="less_wt"
                            class="form-control @error('less_wt') is-invalid @enderror"
                            value="{{ old('less_wt', $editingInvoice?->less_wt ?? 0) }}"
                        >
                        @error('less_wt')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="personal_use" id="personal_use" value="1"
                                {{ old('personal_use', $editingInvoice?->is_personal_use) ? 'checked' : '' }}>
                            <label class="form-check-label" for="personal_use">Personal use (not covered by withholding tax)</label>
                        </div>
                        <div class="form-text" id="wtHint"></div>
                        <button type="button" class="btn btn-link btn-sm p-0 d-none" id="applyWtBtn">Use suggested amount</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="prepared_by" class="form-label">Prepared By</label>
                        <select
                            name="prepared_by"
                            id="prepared_by"
                            class="form-select @error('prepared_by') is-invalid @enderror"
                        >
                            <option value="">-- Select User --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ old('prepared_by', $editingInvoice?->prepared_by ?? auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('prepared_by')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Defaults to you, but can be changed if preparing on someone else's behalf.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="approved_by" class="form-label">Approved By</label>
                        <input
                            type="text"
                            name="approved_by"
                            id="approved_by"
                            class="form-control @error('approved_by') is-invalid @enderror"
                            placeholder="Leave blank if not yet approved"
                            value="{{ old('approved_by', $editingInvoice?->approved_by) }}"
                        >
                        @error('approved_by')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="alert alert-info small">
                    A direct invoice sells from the <strong>POS stock</strong> only. Each item shows its POS stock and
                    its Warehouse stock — stock that is still in the Warehouse has to be transferred to POS first.
                    @if(in_array(Auth::user()->role, ['admin', 'admin_staff'], true))
                        <a href="{{ route('stock-transfers.create') }}" target="_blank">New Stock Transfer</a>
                    @endif
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Line Items</h6>
                    <select id="itemSortSelect" class="form-select form-select-sm" style="width: 160px;">
                        <option value="name_asc">Name (A–Z)</option>
                        <option value="name_desc">Name (Z–A)</option>
                        <option value="code_asc">Code (A–Z)</option>
                    </select>
                </div>

                <div id="lineItemsBody"></div>

                <div class="row justify-content-end mt-4">
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td>VATable Sales</td>
                                    <td class="text-end" id="totalVatSales">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>VAT-Exempt Sales</td>
                                    <td class="text-end" id="totalVatexSales">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>Zero-Rated Sales</td>
                                    <td class="text-end" id="totalZeroSales">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>VAT Amount ({{ number_format($activeVatRate, 2) }}%)</td>
                                    <td class="text-end" id="totalVatAmount">₱0.00</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total Sales</td>
                                    <td class="text-end" id="totalSales">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>Less: VAT (SC/PWD)</td>
                                    <td class="text-end" id="totalLessVat">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>Amount Net of VAT</td>
                                    <td class="text-end" id="totalAmountNet">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>Less: SC/PWD Discount (20%)</td>
                                    <td class="text-end" id="totalLessSc">₱0.00</td>
                                </tr>
                                <tr>
                                    <td>Less: Withholding Tax</td>
                                    <td class="text-end" id="totalLessWt">₱0.00</td>
                                </tr>
                                <tr class="fw-bold fs-5 border-top">
                                    <td>Amount Due</td>
                                    <td class="text-end" id="totalAmountDue">₱0.00</td>
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
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary">Save Invoice</button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
@include('admin.partials.withholding-tax-script')
<script>
    const ITEMS = @json($itemsForJs);
    const PREFILL_LINES = @json($prefillLines);

    const ACTIVE_VAT_RATE = {{ $activeVatRate }};

    const CUSTOMERS = @json($customersForJs);

    // customer_id is only ever set on an exact name match — a half-typed or
    // unmatched name (a genuine walk-in with no Customer record) leaves it
    // null, same as the historical customer_name-only rows.
    function syncCustomerId() {
        const input = document.getElementById('customer_name');
        const hidden = document.getElementById('customer_id');
        const match = CUSTOMERS.find(c => c.name === input.value);
        hidden.value = match ? match.id : '';
        // The customer's withholding rates drive the suggested Withholding Tax.
        computeTotals();
    }

    document.getElementById('customer_name').addEventListener('input', syncCustomerId);
    document.addEventListener('DOMContentLoaded', syncCustomerId);

    let rowIndex = 0;

    // Item is a searchable text field (native <datalist>, matching the same
    // technique already used for Generic Description pickers) instead of a
    // long <select> — the label the user types/picks is matched back to the
    // real product_id.
    function itemLabel(item) {
        return `${item.name} (POS: ${item.quantity}, Warehouse: ${item.warehouse_quantity})`;
    }

    function itemDatalistOptions() {
        return ITEMS.map(i => `<option value="${itemLabel(i)}"></option>`).join('');
    }

    const TAX_LABELS = { vatable: 'VATable', vatex: 'VAT-Exempt', zero: 'Zero-Rated' };

    // The Tax select's first option is "Default" — spell out what that means for
    // the picked item (its product tax), so nobody has to guess.
    function updateDefaultTaxLabel(card, item) {
        const option = card.querySelector('.tax-select option[value=""]');
        if (!option) {
            return;
        }
        const classification = item ? (item.tax_classification || (item.taxable ? 'vatable' : 'vatex')) : null;
        option.textContent = classification ? `Default (${TAX_LABELS[classification]})` : 'Default';
        syncTaxTitle(card);
    }

    function syncTaxTitle(card) {
        const select = card.querySelector('.tax-select');
        select.title = select.selectedOptions.length ? select.selectedOptions[0].textContent : '';
    }

    // Spell out what the stock numbers mean for the picked item, so a product
    // that is in the Warehouse but not at POS doesn't just read as "no stock".
    function updateStockHint(card) {
        const hint = card.querySelector('.stock-hint');
        const itemId = card.querySelector('.item-id-input').value;
        const item = ITEMS.find(i => String(i.id) === String(itemId));
        const qty = parseInt(card.querySelector('.qty-input').value, 10) || 0;
        hint.className = 'form-text stock-hint';
        hint.textContent = '';
        if (!item || qty <= item.quantity) {
            return;
        }
        hint.classList.add('text-danger');
        hint.textContent = item.warehouse_quantity > 0
            ? `Only ${item.quantity} at POS (${item.warehouse_quantity} in the Warehouse). Transfer stock to POS first.`
            : `Only ${item.quantity} at POS, and none in the Warehouse.`;
    }

    function findItemByLabel(label) {
        return ITEMS.find(i => itemLabel(i) === label);
    }

    // "Sort/arrange" toggle — re-sorts the in-memory ITEMS array and rebuilds
    // every already-rendered row's datalist.
    function sortItems(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            ITEMS.sort((a, b) => collator.compare(b.name, a.name));
        } else if (mode === 'code_asc') {
            ITEMS.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            ITEMS.sort((a, b) => collator.compare(a.name, b.name));
        }

        document.querySelectorAll('#lineItemsBody datalist').forEach(dl => {
            dl.innerHTML = itemDatalistOptions();
        });
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
                <div class="col-md-5">
                    <label class="form-label small mb-1">Item</label>
                    <input type="text" class="form-control item-search-input" list="item-list-${index}"
                        placeholder="Search item..." autocomplete="off" required>
                    <datalist id="item-list-${index}">${itemDatalistOptions()}</datalist>
                    <input type="hidden" name="items[${index}][item_id]" class="item-id-input">
                    <div class="form-text stock-hint"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Description</label>
                    <input type="text" name="items[${index}][desc]" class="form-control desc-input">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Unit</label>
                    <input type="text" name="items[${index}][unit]" class="form-control unit-input" value="pc">
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Batch No.</label>
                    <input type="text" name="items[${index}][batch_no]" class="form-control batch-input">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Expiry</label>
                    <input type="date" name="items[${index}][exp]" class="form-control exp-input">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Price</label>
                    <input type="number" name="items[${index}][price]" class="form-control price-input" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Discount</label>
                    <input type="number" name="items[${index}][dis]" class="form-control dis-input" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Tax</label>
                    <select name="items[${index}][tax_override]" class="form-select tax-select">
                        <option value="">Default</option>
                        <option value="vatable">VATable</option>
                        <option value="vatex">VAT-Exempt</option>
                        <option value="zero">Zero-Rated</option>
                    </select>
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

        // A draft's saved line (or a failed-validation redirect's flashed line)
        // is re-applied here. The item is set directly instead of dispatching
        // its input event, which would overwrite the saved price/description/
        // unit with the item's defaults.
        if (prefill.item_id) {
            const item = ITEMS.find(i => String(i.id) === String(prefill.item_id));
            if (item) {
                card.querySelector('.item-search-input').value = itemLabel(item);
                card.querySelector('.item-id-input').value = item.id;
                updateDefaultTaxLabel(card, item);
            }
        }
        const setValue = (selector, value) => {
            if (value !== null && value !== undefined && value !== '') {
                card.querySelector(selector).value = value;
            }
        };
        setValue('.desc-input', prefill.desc);
        setValue('.unit-input', prefill.unit);
        setValue('.batch-input', prefill.batch_no);
        setValue('.exp-input', prefill.exp);
        setValue('.qty-input', prefill.qty);
        setValue('.price-input', prefill.price !== null && prefill.price !== undefined ? Number(prefill.price).toFixed(2) : null);
        setValue('.dis-input', prefill.dis);
        setValue('.tax-select', prefill.tax_override);
        syncTaxTitle(card);
        updateStockHint(card);

        computeTotals();
    }

    function bindRowEvents(card) {
        const itemSearchInput = card.querySelector('.item-search-input');
        const itemIdInput = card.querySelector('.item-id-input');
        const descInput = card.querySelector('.desc-input');
        const unitInput = card.querySelector('.unit-input');
        const priceInput = card.querySelector('.price-input');

        // Description and Unit are pre-filled from the item. Remember that, so
        // picking a different item on the same line refreshes them, while text the
        // encoder typed themselves is never overwritten.
        descInput.addEventListener('input', () => { descInput.dataset.auto = '0'; });
        unitInput.addEventListener('input', () => { unitInput.dataset.auto = '0'; });

        itemSearchInput.addEventListener('input', function () {
            const item = findItemByLabel(this.value);
            itemIdInput.value = item ? item.id : '';
            if (item) {
                priceInput.value = item.price.toFixed(2);
                if (!descInput.value || descInput.dataset.auto === '1') {
                    descInput.value = item.name;
                    descInput.dataset.auto = '1';
                }
                if (!unitInput.value || unitInput.dataset.auto === '1') {
                    unitInput.value = item.unit;
                    unitInput.dataset.auto = '1';
                }
            }
            updateDefaultTaxLabel(card, item);
            updateStockHint(card);
            computeTotals();
        });

        card.querySelectorAll('.qty-input, .price-input, .dis-input, .tax-select').forEach(el => {
            el.addEventListener('input', computeTotals);
            el.addEventListener('change', computeTotals);
        });
        card.querySelector('.qty-input').addEventListener('input', () => updateStockHint(card));
        card.querySelector('.tax-select').addEventListener('change', () => syncTaxTitle(card));

        card.querySelector('.remove-row-btn').addEventListener('click', function () {
            card.remove();
            renumberRows();
            computeTotals();
        });
    }

    function classifyRow(card) {
        const override = card.querySelector('.tax-select').value;
        if (override) {
            return override;
        }
        const itemId = card.querySelector('.item-id-input').value;
        const item = ITEMS.find(i => String(i.id) === String(itemId));
        if (item && item.tax_classification) {
            return item.tax_classification;
        }
        return (item && item.taxable) ? 'vatable' : 'vatex';
    }

    // Withholding Tax suggestion (Sir's formula, see the withholding-tax partial): the
    // item's Product Type (Goods 1% / Services 2%) sets the rate, and a customer with a
    // Withholding VAT % adds that on the VATable part. It is only ever a pre-filled
    // value — the field stays editable, and once someone types in it their number is
    // left alone (the "Use suggested amount" link brings the suggestion back).
    let wtDirty = parseFloat(document.getElementById('less_wt').value) > 0;
    let suggestedWt = null;

    function currentCustomer() {
        const id = document.getElementById('customer_id').value;
        return CUSTOMERS.find(c => String(c.id) === String(id)) || null;
    }

    function refreshWithholdingSuggestion(wtLines) {
        const hint = document.getElementById('wtHint');
        const applyBtn = document.getElementById('applyWtBtn');
        suggestedWt = null;
        hint.textContent = '';
        applyBtn.classList.add('d-none');

        // Nothing to suggest without a chosen customer or without any item lines.
        const result = WithholdingTax.compute(wtLines, currentCustomer(), ACTIVE_VAT_RATE, document.getElementById('personal_use').checked);
        if (!result) {
            return;
        }

        suggestedWt = result.total;
        hint.textContent = result.text;

        if (!wtDirty) {
            document.getElementById('less_wt').value = suggestedWt.toFixed(2);
        } else if (Math.abs((parseFloat(document.getElementById('less_wt').value) || 0) - suggestedWt) > 0.004) {
            applyBtn.classList.remove('d-none');
        }
    }

    function computeTotals() {
        let vatSales = 0, vatexSales = 0, zeroSales = 0, vatAmount = 0;
        const wtLines = [];

        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(card => {
            const qty = parseFloat(card.querySelector('.qty-input').value) || 0;
            const price = parseFloat(card.querySelector('.price-input').value) || 0;
            const dis = parseFloat(card.querySelector('.dis-input').value) || 0;
            const amount = (qty * price) - dis;

            const classification = classifyRow(card);
            const wtItemId = card.querySelector('.item-id-input').value;
            if (wtItemId !== '') {
                const wtItem = ITEMS.find(i => String(i.id) === String(wtItemId));
                wtLines.push({ amount, classification, type: wtItem ? wtItem.product_type : 'goods' });
            }
            // Price is VAT-inclusive (Sir's rule) — VAT is extracted back out
            // of a VATable line's amount here, not added on top (mirrors the
            // PHP computation in InvoiceController::store).
            let lineVat = 0;
            if (classification === 'vatable') {
                const lineNet = amount / (1 + (ACTIVE_VAT_RATE / 100));
                lineVat = amount - lineNet;
                vatSales += lineNet;
                vatAmount += lineVat;
            } else if (classification === 'zero') {
                zeroSales += amount;
            } else {
                vatexSales += amount;
            }

            card.querySelector('.amount-display').textContent = '₱' + amount.toFixed(2);
        });

        const totalSales = vatSales + vatexSales + zeroSales + vatAmount;
        const oscaNo = document.getElementById('osca_no').value.trim();

        let lessVat = 0, amountNet = totalSales, lessSc = 0;
        if (oscaNo) {
            lessVat = vatAmount;
            amountNet = totalSales - lessVat;
            lessSc = vatSales * 0.20;
        }

        // Suggest (and, unless someone typed their own, fill in) the withholding tax
        // before it is subtracted below.
        refreshWithholdingSuggestion(wtLines);

        const lessWt = parseFloat(document.getElementById('less_wt').value) || 0;
        const amountDue = amountNet - lessSc - lessWt;

        document.getElementById('totalVatSales').textContent = '₱' + vatSales.toFixed(2);
        document.getElementById('totalVatexSales').textContent = '₱' + vatexSales.toFixed(2);
        document.getElementById('totalZeroSales').textContent = '₱' + zeroSales.toFixed(2);
        document.getElementById('totalVatAmount').textContent = '₱' + vatAmount.toFixed(2);
        document.getElementById('totalSales').textContent = '₱' + totalSales.toFixed(2);
        document.getElementById('totalLessVat').textContent = '₱' + lessVat.toFixed(2);
        document.getElementById('totalAmountNet').textContent = '₱' + amountNet.toFixed(2);
        document.getElementById('totalLessSc').textContent = '₱' + lessSc.toFixed(2);
        document.getElementById('totalLessWt').textContent = '₱' + lessWt.toFixed(2);
        document.getElementById('totalAmountDue').textContent = '₱' + amountDue.toFixed(2);
    }

    document.getElementById('addRowBtn').addEventListener('click', () => addRow());
    document.getElementById('osca_no').addEventListener('input', computeTotals);
    document.getElementById('less_wt').addEventListener('input', () => {
        wtDirty = true;
        computeTotals();
    });
    // Ticking or unticking Personal use is an explicit choice, so it takes over the field: the
    // suggestion (0 when ticked, the normal one when not) is filled in again.
    document.getElementById('personal_use').addEventListener('change', () => {
        wtDirty = false;
        computeTotals();
    });
    document.getElementById('applyWtBtn').addEventListener('click', () => {
        wtDirty = false;
        computeTotals();
    });

    // One row per saved line of the draft being resumed, or a single empty
    // row for a brand new invoice.
    if (PREFILL_LINES.length > 0) {
        PREFILL_LINES.forEach(line => addRow(line));
    } else {
        addRow();
    }
</script>
@endsection
