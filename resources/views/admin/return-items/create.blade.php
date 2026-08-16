@extends('layout.app')

@section('title', 'New Return Item')

@section('content')
    <div class="card mt-3">
        <h5 class="card-header">New Return Item</h5>
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

            <form action="{{ route('return-items.store') }}" method="POST" id="returnItemForm">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">— No customer / no credit —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->customer_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="customerHint">Once a customer is selected, the Item field only offers what they've actually been invoiced for — so a return can't be recorded against something they never bought.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <select name="reason" id="reason" class="form-select" required>
                            <option value="">-- Select Reason --</option>
                            <option value="Defective Product">Defective Product</option>
                            <option value="Wrong Item Received">Wrong Item Received</option>
                            <option value="Not as Described">Not as Described</option>
                            <option value="Changed Mind">Changed Mind</option>
                            <option value="Damaged During Delivery">Damaged During Delivery</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <hr>

                <div id="noPurchasesWarning" class="alert alert-warning d-none">This customer has no recorded purchases (invoiced sales) to return against.</div>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="itemSearchInput" list="itemList"
                            placeholder="Search item..." autocomplete="off" required>
                        <datalist id="itemList"></datalist>
                        <input type="hidden" name="product_batch_id" id="productBatchIdInput">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Purchased</label>
                        <input type="text" class="form-control" id="totalPurchasedDisplay" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Lot/Batch No.</label>
                        <input type="text" class="form-control" id="batchInput" list="batchList" autocomplete="off">
                        <datalist id="batchList"></datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Expiry</label>
                        <input type="text" class="form-control" id="expiryDisplay" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="quantityInput" class="form-control" min="1" value="1" required>
                    </div>
                </div>
                <div class="form-text mt-1" id="pickHint">Pick an item, then choose its batch — the batch is required before this return can be saved.</div>

                <div class="mb-3 mt-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Add any additional details...">{{ old('notes') }}</textarea>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Return Item</button>
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
    const customerSelect = document.getElementById('customer_id');
    const noPurchasesWarning = document.getElementById('noPurchasesWarning');

    customerSelect.addEventListener('change', async function () {
        clearItemSelection();
        noPurchasesWarning.classList.add('d-none');

        if (!this.value) {
            PRODUCTS = ALL_PRODUCTS;
            rebuildItemList();
            return;
        }

        itemSearchInput.disabled = true;
        itemSearchInput.placeholder = 'Loading this customer\'s purchases…';

        try {
            const res = await fetch(`/api/customers/${this.value}/purchased-items`);
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
        if (!productBatchIdInput.value) {
            e.preventDefault();
            alert('Select the item\'s specific lot/batch before saving.');
        }
    });
</script>
@endsection
