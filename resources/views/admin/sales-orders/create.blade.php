@extends(Auth::user()->role === 'admin' ? 'layout.app' : 'layout.user')

@section('title', 'New Sales Order')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Sales Order</h5>
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

            <form action="{{ route('sales-orders.store') }}" method="POST" id="salesOrderForm">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select
                            name="customer_id"
                            id="customer_id"
                            class="form-select @error('customer_id') is-invalid @enderror"
                            required
                        >
                            <option value="">-- Select Customer --</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" data-price-level="{{ $customer->price_level }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->customer_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if(Auth::user()->role === 'admin')
                            <div class="form-text">Don't see the customer? <a href="{{ route('customers.index') }}" target="_blank">Add one here</a>.</div>
                        @endif
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="po_no" class="form-label">Customer P.O. #</label>
                        <input
                            type="text"
                            name="po_no"
                            id="po_no"
                            class="form-control @error('po_no') is-invalid @enderror"
                            value="{{ old('po_no') }}"
                        >
                        @error('po_no')
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
                            value="{{ old('order_date', now()->toDateString()) }}"
                            required
                        >
                        @error('order_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
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
                                <option value="{{ $user->id }}" {{ old('prepared_by', auth()->id()) == $user->id ? 'selected' : '' }}>
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
                    <button type="submit" class="btn btn-primary">Save Sales Order</button>
                    <a href="{{ route('sales-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const GENERIC_NAMES = @json($genericNamesForJs);

    let rowIndex = 0;

    // Generic Description is a searchable text field (native <datalist>,
    // matching the same technique already used for the batch-number picker
    // in Inventory Adjustment) rather than a long <select> — the label the
    // user types/picks is matched back to the real generic_name_id.
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
    // rebuilds every already-rendered row's datalist.
    function sortGenericNames(mode) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
        if (mode === 'name_desc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(b.generic_name, a.generic_name));
        } else if (mode === 'code_asc') {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.code || '', b.code || ''));
        } else {
            GENERIC_NAMES.sort((a, b) => collator.compare(a.generic_name, b.generic_name));
        }

        document.querySelectorAll('#lineItemsBody datalist').forEach(dl => {
            dl.innerHTML = genericDatalistOptions();
        });
    }

    document.getElementById('itemSortSelect').addEventListener('change', function () {
        sortGenericNames(this.value);
    });

    function currentPriceLevel() {
        const select = document.getElementById('customer_id');
        const selected = select.options[select.selectedIndex];
        return selected ? (selected.getAttribute('data-price-level') || 'retail') : 'retail';
    }

    function renumberRows() {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach((card, i) => {
            card.querySelector('.line-item-number').textContent = 'Item #' + (i + 1);
        });
    }

    function addRow() {
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
                    <label class="form-label small mb-1">Generic Description</label>
                    <input type="text" class="form-control generic-search-input" list="generic-list-${index}"
                        placeholder="Search generic name..." autocomplete="off" required>
                    <datalist id="generic-list-${index}">${genericDatalistOptions()}</datalist>
                    <input type="hidden" name="items[${index}][generic_name_id]" class="generic-id-input">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" value="1" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Price</label>
                    <input type="number" name="items[${index}][price]" class="form-control price-input" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Advance Order Qty</label>
                    <input type="number" name="items[${index}][advance_order_qty]" class="form-control advance-input" min="0" value="0">
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
        computeTotals();
    }

    function bindRowEvents(card) {
        const genericSearchInput = card.querySelector('.generic-search-input');
        const genericIdInput = card.querySelector('.generic-id-input');
        const priceInput = card.querySelector('.price-input');

        genericSearchInput.addEventListener('input', function () {
            const generic = findGenericByLabel(this.value);
            genericIdInput.value = generic ? generic.id : '';

            if (generic) {
                const price = generic.prices[currentPriceLevel()];
                if (price !== null && price !== undefined) {
                    priceInput.value = parseFloat(price).toFixed(2);
                }
            }
            computeTotals();
        });

        card.querySelectorAll('.qty-input, .price-input, .advance-input').forEach(el => {
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
            const price = parseFloat(card.querySelector('.price-input').value) || 0;
            const amount = qty * price;
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

    // Re-suggest prices for all rows when the customer (and thus customer type) changes
    document.getElementById('customer_id').addEventListener('change', function () {
        document.querySelectorAll('#lineItemsBody .line-item-card').forEach(card => {
            const genericIdInput = card.querySelector('.generic-id-input');
            const priceInput = card.querySelector('.price-input');
            const generic = GENERIC_NAMES.find(g => String(g.id) === String(genericIdInput.value));
            if (generic) {
                const price = generic.prices[currentPriceLevel()];
                if (price !== null && price !== undefined) {
                    priceInput.value = parseFloat(price).toFixed(2);
                }
            }
        });
        computeTotals();
    });

    // Start with one empty row
    addRow();
</script>
@endsection
