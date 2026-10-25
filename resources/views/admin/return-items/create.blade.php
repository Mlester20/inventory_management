@extends('layout.app')

@section('title', isset($editingReturnItem) ? 'Edit Draft Return Item' : 'New Return Item')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">{{ isset($editingReturnItem) ? 'Edit Draft Return Item' : 'New Return Item' }}</h5>
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

            <form action="{{ isset($editingReturnItem) ? route('return-items.update', $editingReturnItem) : route('return-items.store') }}" method="POST" id="returnItemForm">
                @csrf
                @if(isset($editingReturnItem))
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="customer_search" class="form-label">Customer</label>
                        <input type="text" name="customer_search" id="customer_search" class="form-control"
                            list="customer_datalist" placeholder="Search customer, or leave blank for no customer / no credit"
                            autocomplete="off" value="{{ old('customer_search', $editingReturnItem?->customer?->customer_name ?? '') }}">
                        <datalist id="customer_datalist">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->customer_name }}"></option>
                            @endforeach
                        </datalist>
                        <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $editingReturnItem?->customer_id ?? '') }}">
                        <div class="form-text" id="customerHint">Once a customer is selected, the Item field only offers what they've actually been invoiced for — so a return can't be recorded against something they never bought.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        @include('admin.partials.prepared-by-select', ['selected' => $editingReturnItem?->prepared_by ?? auth()->id()])
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', $editingReturnItem?->return_date ?? now()->toDateString()) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <select name="reason" id="reason" class="form-select">
                            <option value="">-- Select Reason --</option>
                            @foreach(['Defective Product', 'Wrong Item Received', 'Not as Described', 'Changed Mind', 'Damaged During Delivery', 'Other'] as $reasonOption)
                                <option value="{{ $reasonOption }}" {{ old('reason', $editingReturnItem?->reason ?? '') === $reasonOption ? 'selected' : '' }}>{{ $reasonOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr>

                <div id="noPurchasesWarning" class="alert alert-warning d-none">This customer has no recorded purchases (invoiced sales) to return against.</div>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="itemSearchInput" list="itemList"
                            placeholder="Search item..." autocomplete="off"
                            @php
                                $editingProduct = $editingReturnItem?->productBatch?->product;
                                $editingItemLabel = $editingProduct ? ($editingProduct->description ?: $editingProduct->item_name) : '';
                            @endphp
                            value="{{ old('item_search', $editingItemLabel) }}">
                        <datalist id="itemList"></datalist>
                        <input type="hidden" name="product_batch_id" id="productBatchIdInput" value="{{ old('product_batch_id', $editingReturnItem?->product_batch_id ?? '') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Purchased</label>
                        <input type="text" class="form-control" id="totalPurchasedDisplay" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Lot/Batch No.</label>
                        <input type="text" class="form-control" id="batchInput" list="batchList" autocomplete="off"
                            value="{{ $editingReturnItem?->productBatch?->batch_no ?? '' }}">
                        <datalist id="batchList"></datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Expiry</label>
                        <input type="text" class="form-control" id="expiryDisplay" readonly
                            value="{{ $editingReturnItem?->productBatch?->expiration_date?->format('Y-m-d') ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="quantityInput" class="form-control" min="1" value="{{ old('quantity', $editingReturnItem?->quantity ?? 1) }}">
                    </div>
                </div>
                <div class="form-text mt-1" id="pickHint">Pick an item, then choose its batch — the batch is required before this return can be submitted (Save Draft works without it).</div>

                <div class="mb-3 mt-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Add any additional details...">{{ old('notes', $editingReturnItem?->notes ?? '') }}</textarea>
                </div>

                <div class="mt-3">
                    <button type="submit" name="save_action" value="draft" formnovalidate class="btn btn-outline-secondary">
                        <i class="bx bx-save"></i> Save Draft
                    </button>
                    <button type="submit" name="save_action" value="posted" class="btn btn-primary"
                        @disabled(Auth::user()->role !== 'admin')
                        @if(Auth::user()->role !== 'admin') title="Finalizing is restricted to full admin accounts — use Save Draft instead." @endif>
                        Save Return Item
                    </button>
                    <a href="{{ route('return-items.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Full catalog — the fallback item list when no customer is picked
    // (e.g. a truly anonymous cash-refund return). Once a customer IS
    // selected, PRODUCTS is swapped for just what that customer has
    // actually been invoiced for (fetched from the customers API), so the
    // Item field can never offer something they never bought.
    const ALL_PRODUCTS = @json($productsForJs);
    let PRODUCTS = ALL_PRODUCTS;

    const CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->customer_name])->values());

    function findCustomerByName(name) {
        return CUSTOMERS.find(c => c.name === name) || null;
    }

    function productLabel(p) {
        return p.name;
    }

    function findProductByLabel(label) {
        return PRODUCTS.find(p => productLabel(p) === label);
    }

    function rebuildItemList() {
        document.getElementById('itemList').innerHTML =
            PRODUCTS.map(p => `<option value="${productLabel(p)}"></option>`).join('');
    }
    rebuildItemList();

    const itemSearchInput = document.getElementById('itemSearchInput');
    const productBatchIdInput = document.getElementById('productBatchIdInput');
    const batchInput = document.getElementById('batchInput');
    const batchList = document.getElementById('batchList');
    const expiryDisplay = document.getElementById('expiryDisplay');
    const totalPurchasedDisplay = document.getElementById('totalPurchasedDisplay');

    function clearItemSelection() {
        itemSearchInput.value = '';
        productBatchIdInput.value = '';
        batchInput.value = '';
        expiryDisplay.value = '';
        totalPurchasedDisplay.value = '';
        batchList.innerHTML = '';
    }

    itemSearchInput.addEventListener('input', function () {
        const product = findProductByLabel(this.value);
        productBatchIdInput.value = '';
        batchInput.value = '';
        expiryDisplay.value = '';
        batchList.innerHTML = '';
        totalPurchasedDisplay.value = (product && product.total_purchased !== undefined)
            ? `${product.total_purchased} ${product.unit || ''}`.trim()
            : '';

        if (product) {
            product.batches.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.batch_no || '';
                batchList.appendChild(opt);
            });
        }
    });

    batchInput.addEventListener('input', function () {
        const product = findProductByLabel(itemSearchInput.value);
        productBatchIdInput.value = '';
        expiryDisplay.value = '';
        if (!product) return;

        const match = product.batches.find(b => b.batch_no === this.value);
        if (match) {
            productBatchIdInput.value = match.id;
            expiryDisplay.value = match.expiration_date || '';
        }
    });

    // Swap the Item field's options to this customer's own purchase
    // history whenever the customer changes; revert to the full catalog
    // when the customer is cleared. Any already-picked item/batch is reset,
    // since it may no longer be valid at the new scope.
    const customerSearchInput = document.getElementById('customer_search');
    const customerIdInput = document.getElementById('customer_id');
    const noPurchasesWarning = document.getElementById('noPurchasesWarning');

    customerSearchInput.addEventListener('input', async function () {
        const match = findCustomerByName(this.value);
        customerIdInput.value = match ? match.id : '';

        clearItemSelection();
        noPurchasesWarning.classList.add('d-none');

        if (!match) {
            PRODUCTS = ALL_PRODUCTS;
            rebuildItemList();
            return;
        }

        itemSearchInput.disabled = true;
        itemSearchInput.placeholder = 'Loading this customer\'s purchases…';

        try {
            const res = await fetch(`/api/customers/${match.id}/purchased-items`);
            const data = await res.json();
            PRODUCTS = data.items || [];
            rebuildItemList();
            noPurchasesWarning.classList.toggle('d-none', PRODUCTS.length > 0);
        } finally {
            itemSearchInput.disabled = false;
            itemSearchInput.placeholder = 'Search item...';
        }
    });

    document.getElementById('returnItemForm').addEventListener('submit', function (e) {
        // Save Draft intentionally allows an incomplete line — only the
        // real "Save Return Item" submit requires a resolved batch.
        const isDraftSave = e.submitter && e.submitter.value === 'draft';
        if (!isDraftSave && !productBatchIdInput.value) {
            e.preventDefault();
            alert('Select the item\'s specific lot/batch before saving.');
        }
    });
</script>
@endsection
