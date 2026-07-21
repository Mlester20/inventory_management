@extends('layout.app')

@section('title', 'New Delivery Receipt')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Delivery Receipt</h5>
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

            <form action="{{ route('delivery-receipts.store') }}" method="POST" id="deliveryReceiptForm">
                @csrf
                <input type="hidden" name="transaction_type" id="transaction_type" value="advance_order">

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
                    <button type="button" class="btn btn-outline-primary active" id="tabBtnAdvance" onclick="showTab('advance_order')">
                        Advance Order
                    </button>
                    <button type="button" class="btn btn-outline-info" id="tabBtnPurchase" onclick="showTab('purchase_order')">
                        Purchase Order
                    </button>
                </div>

                <!-- Advance Order Tab -->
                <div id="advance_order_tab" class="do-tab">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ao_customer_id" class="form-label">Customer</label>
                            <div class="input-group">
                                <select name="customer_id" id="ao_customer_id" class="form-select">
                                    <option value="">-- Select Customer --</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                                    @endforeach
                                </select>
                                @if(Auth::user()->role === 'admin')
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                        New?
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Generic Items</h6>
                        <button type="button" class="btn btn-sm btn-primary" id="addAoRowBtn">
                            <i class="bx bx-plus"></i> Add Generic
                        </button>
                    </div>
                    <div id="aoLineItemsBody"></div>
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

                    <h6 class="mb-2">Remaining Generic Lines</h6>
                    <div id="poLineItemsBody"></div>
                    <div id="poEmptyMessage" class="alert alert-info d-none">Select a Sales Order to load its remaining lines.</div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Delivery Receipt</button>
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
                        <select id="nc_customer_type" class="form-select">
                            @foreach (\App\Models\Customer::CUSTOMER_TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" id="nc_contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" id="nc_phone" class="form-control">
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

    function showTab(tab) {
        document.getElementById('transaction_type').value = tab;
        document.getElementById('advance_order_tab').style.display = tab === 'advance_order' ? 'block' : 'none';
        document.getElementById('purchase_order_tab').style.display = tab === 'purchase_order' ? 'block' : 'none';
        document.getElementById('tabBtnAdvance').classList.toggle('active', tab === 'advance_order');
        document.getElementById('tabBtnPurchase').classList.toggle('active', tab === 'purchase_order');
    }

    function itemOptionsHtml(items) {
        if (items.length === 0) {
            return null;
        }
        let html = '<option value="">-- Select Item --</option>';
        items.forEach(item => {
            html += `<option value="${item.id}" data-max="${item.quantity}">${item.brand_name} — Batch ${item.batch_no || 'N/A'} (Stock: ${item.quantity}${item.expiration_date ? ', Exp: ' + item.expiration_date : ''})</option>`;
        });
        return html;
    }

    async function fetchAvailableItems(genericNameId) {
        const res = await fetch(`/api/generic-names/${genericNameId}/available-items`);
        return res.json();
    }

    // ---------- Advance Order tab ----------

    function addAoRow() {
        const index = aoRowIndex++;
        const card = document.createElement('div');
        card.className = 'line-item-card border rounded p-3 mb-3';
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-muted">Generic Item</span>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bx bx-trash"></i> Remove
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Generic Description</label>
                    <select class="form-select ao-generic-select" required>
                        <option value="">-- Select Generic Name --</option>
                        @foreach ($genericNames as $g)
                            <option value="{{ $g->id }}">{{ $g->generic_name }} ({{ $g->unit }}) — {{ $g->category->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7 availability-area">
                    <div class="text-muted small">Select a generic to check availability.</div>
                </div>
            </div>
        `;
        document.getElementById('aoLineItemsBody').appendChild(card);

        card.querySelector('.remove-row-btn').addEventListener('click', () => card.remove());

        const genericSelect = card.querySelector('.ao-generic-select');
        genericSelect.addEventListener('change', async function () {
            const area = card.querySelector('.availability-area');
            if (!this.value) {
                area.innerHTML = '<div class="text-muted small">Select a generic to check availability.</div>';
                return;
            }
            area.innerHTML = '<div class="text-muted small">Checking availability…</div>';
            const items = await fetchAvailableItems(this.value);
            renderAvailability(area, items, index, this.value, null, null);
        });
    }

    function renderAvailability(area, items, index, genericNameId, salesOrderItemId, remainingQty) {
        const optionsHtml = itemOptionsHtml(items);

        if (!optionsHtml) {
            area.innerHTML = `
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bx bx-error"></i> No stock available for this generic — will be skipped.
                </div>
            `;
            return;
        }

        area.innerHTML = `
            <div class="row g-2">
                <div class="col-md-8">
                    <label class="form-label small mb-1">Item (Brand / Batch)</label>
                    <select class="form-select item-select" name="items[${index}][item_id]">${optionsHtml}</select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Qty</label>
                    <input type="number" class="form-control qty-input" name="items[${index}][qty]" min="1" value="1">
                </div>
            </div>
        `;

        if (salesOrderItemId) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `items[${index}][sales_order_item_id]`;
            hidden.value = salesOrderItemId;
            area.appendChild(hidden);
        }

        const itemSelect = area.querySelector('.item-select');
        const qtyInput = area.querySelector('.qty-input');

        function clampQty() {
            const selected = itemSelect.options[itemSelect.selectedIndex];
            const maxStock = selected ? parseInt(selected.getAttribute('data-max') || '0', 10) : 0;
            const cap = remainingQty ? Math.min(maxStock, remainingQty) : maxStock;
            qtyInput.max = cap || 1;
            if (parseInt(qtyInput.value, 10) > cap) {
                qtyInput.value = cap || 1;
            }
        }
        itemSelect.addEventListener('change', clampQty);
        clampQty();
    }

    document.getElementById('addAoRowBtn').addEventListener('click', addAoRow);

    // ---------- Purchase Order tab ----------

    document.getElementById('po_sales_order_id').addEventListener('change', async function () {
        const soId = this.value;
        const body = document.getElementById('poLineItemsBody');
        const emptyMsg = document.getElementById('poEmptyMessage');
        body.innerHTML = '';
        document.getElementById('po_customer_display').value = '';

        if (!soId) {
            emptyMsg.classList.remove('d-none');
            return;
        }

        const res = await fetch(`/api/sales-orders/${soId}/remaining-items`);
        const data = await res.json();

        document.getElementById('po_customer_display').value = data.customer.customer_name;

        if (data.items.length === 0) {
            emptyMsg.textContent = 'This Sales Order has no remaining lines to deliver.';
            emptyMsg.classList.remove('d-none');
            return;
        }
        emptyMsg.classList.add('d-none');

        for (const line of data.items) {
            const index = poRowIndex++;
            const card = document.createElement('div');
            card.className = 'line-item-card border rounded p-3 mb-3';
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">${line.generic_name}</span>
                    <span class="badge bg-warning text-dark">Remaining: ${line.remaining_qty}</span>
                </div>
                <div class="availability-area">
                    <div class="text-muted small">Checking availability…</div>
                </div>
            `;
            body.appendChild(card);

            const area = card.querySelector('.availability-area');
            const items = await fetchAvailableItems(line.generic_name_id);
            renderAvailability(area, items, index, line.generic_name_id, line.sales_order_item_id, line.remaining_qty);
        }
    });

    // Auto-trigger the Purchase Order tab load if a sales_order_id was preselected via the URL
    @if($preselectedSalesOrderId)
        showTab('purchase_order');
        document.getElementById('po_sales_order_id').dispatchEvent(new Event('change'));
    @endif

    // ---------- New Customer modal ----------

    document.getElementById('saveNewCustomerBtn').addEventListener('click', async function () {
        const errorBox = document.getElementById('newCustomerError');
        errorBox.classList.add('d-none');

        const payload = {
            customer_name: document.getElementById('nc_customer_name').value,
            customer_type: document.getElementById('nc_customer_type').value,
            contact_person: document.getElementById('nc_contact_person').value,
            phone: document.getElementById('nc_phone').value,
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
        const inactiveTabEl = document.getElementById(activeTab === 'advance_order' ? 'purchase_order_tab' : 'advance_order_tab');
        inactiveTabEl.querySelectorAll('input, select').forEach(el => el.disabled = true);
    });

    // Start with one empty Advance Order row
    addAoRow();
</script>
@endsection
