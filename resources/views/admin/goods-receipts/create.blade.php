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
                        <button type="button" class="btn btn-sm btn-primary" id="addDirectRowBtn">
                            <i class="bx bx-plus"></i> Add Item
                        </button>
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

    function itemOptions(selectedId = '') {
        let html = '<option value="">-- Select Item --</option>';
        const supplierId = currentDirectSupplierId();

        ITEMS.forEach(item => {
            if (supplierId && String(item.supplier_id) !== String(supplierId)) {
                return;
            }
            const selected = String(item.id) === String(selectedId) ? 'selected' : '';
            html += `<option value="${item.id}" ${selected}>${item.name}</option>`;
        });
        return html;
    }

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
                    <select name="items[${index}][product_id]" class="form-select item-select" required>
                        ${itemOptions()}
                    </select>
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

        const itemSelect = card.querySelector('.item-select');
        const costInput = card.querySelector('.cost-input');
        itemSelect.addEventListener('change', function () {
            const item = ITEMS.find(i => String(i.id) === String(this.value));
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

    document.getElementById('direct_supplier_id').addEventListener('change', function () {
        document.querySelectorAll('#directLineItemsBody .item-select').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = itemOptions(currentValue);
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
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">${line.item_name}</span>
                    <span class="badge bg-warning text-dark">Remaining: ${line.remaining_qty}</span>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Qty</label>
                        <input type="number" name="items[${index}][qty]" class="form-control qty-input" min="1" max="${line.remaining_qty}" value="${line.remaining_qty}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Unit Cost</label>
                        <input type="number" name="items[${index}][unit_cost]" class="form-control" step="0.01" min="0" value="${parseFloat(line.unit_cost).toFixed(2)}">
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
                <input type="hidden" name="items[${index}][product_id]" value="${line.product_id}">
                <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${line.purchase_order_item_id}">
            `;
            body.appendChild(card);

            const qtyInput = card.querySelector('.qty-input');
            qtyInput.addEventListener('input', function () {
                if (parseInt(this.value, 10) > line.remaining_qty) {
                    this.value = line.remaining_qty;
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
