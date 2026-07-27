@extends('layout.user')

@section('title', 'Point of Sale')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">

        {{-- Left: Items Panel --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Available Items</h5>
                    <input type="text" id="searchItems" class="form-control form-control-sm"
                        style="width: 200px;" placeholder="Search items...">
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-barcode-reader"></i></span>
                            <input type="text" id="barcodeInput" class="form-control form-control-lg"
                                placeholder="Scan barcode..." autocomplete="off">
                        </div>
                    </div>
                    <div class="row" id="itemsContainer">
                        <div class="text-center text-muted py-5">
                            <i class="bx bx-loader bx-spin fs-1"></i>
                            <p>Loading items...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Cart Panel --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title m-0">Shopping Cart</h5>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <div id="cartItems">
                        <p class="text-muted text-center">Cart is empty</p>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row mb-2">
                        <div class="col-6"><span class="text-muted">Subtotal:</span></div>
                        <div class="col-6 text-end"><strong id="subtotal">₱0.00</strong></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6"><span class="text-muted">Total Items:</span></div>
                        <div class="col-6 text-end"><strong id="totalItems">0</strong></div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-6"><span class="fw-bold">TOTAL:</span></div>
                        <div class="col-6 text-end"><h5 class="mb-0" id="totalPrice">₱0.00</h5></div>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" id="checkoutBtn">
                            <i class="bx bx-check me-2"></i>Process Order/s
                        </button>
                        <button class="btn btn-outline-secondary" id="clearCartBtn">
                            <i class="bx bx-trash me-2"></i>Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Payment Modal — Cash Tendered / Change, confirmed before checkout fires --}}
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Total Due</span>
                    <h4 class="mb-0" id="paymentTotalDue">₱0.00</h4>
                </div>
                <div class="mb-3">
                    <label for="amountTenderedInput" class="form-label">Cash Tendered</label>
                    <input type="number" id="amountTenderedInput" class="form-control form-control-lg"
                        step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Change</span>
                    <h5 class="mb-0" id="paymentChange">₱0.00</h5>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmPaymentBtn">
                    <i class="bx bx-check"></i> Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Receipt Modal — shown right after a successful checkout --}}
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header no-print">
                <h5 class="modal-title">Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptContent" class="receipt-sheet"></div>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printReceiptBtn">
                    <i class="bx bx-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // CSRF setup for Axios
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Global state
    let cart = [];
    let items = [];

    // Load all available items
    async function loadItems() {
        try {
            const response = await axios.get('/api/items');
            items = response.data;
            renderItems(items);
        } catch (error) {
            console.error('Error loading items:', error);
            Swal.fire('Error', 'Failed to load items', 'error');
        }
    }

    // Render items grid
    function renderItems(itemsToRender) {
        const container = document.getElementById('itemsContainer');

        if (itemsToRender.length === 0) {
            container.innerHTML = '<div class="col-12"><p class="text-center text-muted py-4">No items available</p></div>';
            return;
        }

        container.innerHTML = itemsToRender.map(item => {
            const isAvailable = item.quantity > 0;
            return '<div class="col-md-6 col-lg-4 mb-3">' +
                '<div class="card h-100 items-card' + (item.quantity === 0 ? ' opacity-50' : '') + '"' +
                (isAvailable ? ' onclick="addItemToCartDirect(' + item.id + ')"' : '') + '>' +
                '<div class="card-body">' +
                '<h6 class="card-title">' + item.item_name + '</h6>' +
                '<p class="text-muted small mb-2">' + item.category.category_name + '</p>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<div>' +
                '<p class="mb-0"><strong>₱' + parseFloat(item.unit_price).toFixed(2) + '</strong></p>' +
                '<small class="text-muted">Stock: ' + item.quantity + '</small>' +
                '</div>' +
                '<span class="badge ' + (isAvailable ? 'bg-success' : 'bg-danger') + '">' +
                (isAvailable ? 'Available' : 'Out of Stock') +
                '</span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    // Shared cart-add logic — used by both manual item clicks and barcode
    // scans, so both paths behave identically (same stock-limit check, same
    // cart line shape). Returns false if the stock limit blocks it.
    function addProductToCart(product, quantity) {
        const existing = cart.find(ci => ci.product_id === product.id);
        if (existing) {
            const newQty = existing.quantity + quantity;
            if (newQty > product.quantity) {
                Swal.fire('Stock Limit', 'Only ' + product.quantity + ' units available in total', 'warning');
                return false;
            }
            existing.quantity = newQty;
            existing.total_price = newQty * existing.unit_price;
        } else {
            cart.push({
                id: Date.now(),
                product_id: product.id,
                item_name: product.item_name,
                unit_price: parseFloat(product.unit_price),
                quantity: quantity,
                total_price: quantity * parseFloat(product.unit_price),
                max_stock: product.quantity,
                taxable: !!product.taxable,
            });
        }

        updateCart();
        return true;
    }

    // Clicking an item card adds 1 unit straight to the cart — no quantity
    // modal. Click again (or use the cart's +/- controls) to add more.
    // No "Added to Cart" popup — the cart panel on the right updates
    // immediately, so a separate alert would just be redundant noise.
    function addItemToCartDirect(itemId) {
        const product = items.find(i => i.id === itemId);

        if (!product || product.quantity === 0) {
            Swal.fire('Out of Stock', 'This item is not available', 'warning');
            return;
        }

        addProductToCart(product, 1);
        focusBarcodeInput();
    }

    // Update cart display
    function updateCart() {
        const cartContainer = document.getElementById('cartItems');

        if (cart.length === 0) {
            cartContainer.innerHTML = '<p class="text-muted text-center">Cart is empty</p>';
            document.getElementById('subtotal').textContent = '₱0.00';
            document.getElementById('totalItems').textContent = '0';
            document.getElementById('totalPrice').textContent = '₱0.00';
            return;
        }

        let totalAmount = 0;
        let totalQty = 0;

        cartContainer.innerHTML = cart.map(item => {
            totalAmount += item.total_price;
            totalQty += item.quantity;
            return '<div class="cart-item mb-3 pb-3 border-bottom">' +
                '<div class="d-flex justify-content-between align-items-start mb-1">' +
                '<div>' +
                '<h6 class="mb-0">' + item.item_name + '</h6>' +
                '<small class="text-muted">₱' + item.unit_price.toFixed(2) + ' each</small>' +
                '</div>' +
                '<button class="btn btn-sm btn-danger" onclick="removeFromCart(' + item.id + ')">' +
                '<i class="bx bx-trash"></i>' +
                '</button>' +
                '</div>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<div class="btn-group">' +
                '<button class="btn btn-outline-secondary btn-sm" onclick="decrementQuantity(' + item.id + ')">−</button>' +
                '<input type="number" class="form-control form-control-sm text-center cart-qty-input" ' +
                'style="width:56px;" min="1" max="' + item.max_stock + '" value="' + item.quantity + '" ' +
                'onchange="setQuantity(' + item.id + ', this.value)">' +
                '<button class="btn btn-outline-secondary btn-sm" onclick="incrementQuantity(' + item.id + ')">+</button>' +
                '</div>' +
                '<strong>₱' + item.total_price.toFixed(2) + '</strong>' +
                '</div>' +
                '</div>';
        }).join('');

        document.getElementById('subtotal').textContent = '₱' + totalAmount.toFixed(2);
        document.getElementById('totalItems').textContent = totalQty;
        document.getElementById('totalPrice').textContent = '₱' + totalAmount.toFixed(2);
    }

    function removeFromCart(cartItemId) {
        cart = cart.filter(i => i.id !== cartItemId);
        updateCart();
    }

    function incrementQuantity(cartItemId) {
        const item = cart.find(i => i.id === cartItemId);
        if (item && item.quantity < item.max_stock) {
            item.quantity += 1;
            item.total_price = item.quantity * item.unit_price;
            updateCart();
        }
    }

    function decrementQuantity(cartItemId) {
        const item = cart.find(i => i.id === cartItemId);
        if (item && item.quantity > 1) {
            item.quantity -= 1;
            item.total_price = item.quantity * item.unit_price;
            updateCart();
        }
    }

    // Typing a quantity directly into the cart's qty field — same stock-limit
    // rule as the +/- buttons, just settable in one go instead of clicking N times.
    function setQuantity(cartItemId, value) {
        const item = cart.find(i => i.id === cartItemId);
        if (!item) return;

        let qty = parseInt(value, 10);

        if (isNaN(qty) || qty < 1) {
            qty = 1;
        } else if (qty > item.max_stock) {
            Swal.fire('Stock Limit', 'Only ' + item.max_stock + ' units available', 'warning');
            qty = item.max_stock;
        }

        item.quantity = qty;
        item.total_price = item.quantity * item.unit_price;
        updateCart();
    }

    // Search items
    document.getElementById('searchItems').addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        renderItems(items.filter(i =>
            i.item_name.toLowerCase().includes(term) ||
            i.category.category_name.toLowerCase().includes(term)
        ));
    });

    // Barcode scanning — physical USB/Bluetooth scanners behave as keyboard
    // input, typing the barcode followed by Enter. A keystroke-timing buffer
    // distinguishes scanner input (a few ms per character) from human typing,
    // since both look identical to the browser otherwise.
    const barcodeInput = document.getElementById('barcodeInput');
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();

    function focusBarcodeInput() {
        barcodeInput.focus();
    }

    barcodeInput.addEventListener('keydown', function (e) {
        const now = Date.now();
        if (now - lastKeyTime > 50 && barcodeBuffer.length > 0) barcodeBuffer = '';
        lastKeyTime = now;

        if (e.key === 'Enter') {
            e.preventDefault();
            if (barcodeBuffer.length > 0) {
                addItemByBarcode(barcodeBuffer);
                barcodeBuffer = '';
                barcodeInput.value = '';
            }
            return;
        }
        if (e.key.length === 1) barcodeBuffer += e.key;
    });

    async function addItemByBarcode(code) {
        try {
            const response = await axios.get('/api/items/barcode/' + encodeURIComponent(code));
            const product = response.data;

            // Scans add 1 unit directly (no quantity modal, no "Added"
            // popup) — scan the same item again to add more, same as a
            // physical POS terminal; the cart panel updating is feedback enough.
            addProductToCart(product, 1);
        } catch (error) {
            const message = error.response?.data?.message || ('Barcode not recognized: ' + code);
            Swal.fire({
                icon: 'error',
                title: 'Scan Error',
                text: message,
                timer: 2000,
                showConfirmButton: false,
            });
        } finally {
            focusBarcodeInput();
        }
    }

    // Clear cart
    document.getElementById('clearCartBtn').addEventListener('click', () => {
        Swal.fire({
            title: 'Clear Cart?',
            text: 'Are you sure you want to remove all items?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, clear it!'
        }).then(result => {
            if (result.isConfirmed) {
                cart = [];
                updateCart();
            }
        });
    });

    // Checkout — clicking "Process Order/s" opens the Payment modal first;
    // the actual /api/purchases call only fires once cash tendered is confirmed.
    let cartTotalForPayment = 0;

    document.getElementById('checkoutBtn').addEventListener('click', () => {
        if (cart.length === 0) {
            Swal.fire('Empty Cart', 'Please add items before checkout', 'warning');
            return;
        }

        cartTotalForPayment = round2(cart.reduce((sum, item) => sum + item.total_price, 0));
        document.getElementById('paymentTotalDue').textContent = '₱' + cartTotalForPayment.toFixed(2);
        document.getElementById('amountTenderedInput').value = '';
        document.getElementById('paymentChange').textContent = '₱0.00';

        new bootstrap.Modal(document.getElementById('paymentModal')).show();
    });

    document.getElementById('paymentModal').addEventListener('shown.bs.modal', () => {
        document.getElementById('amountTenderedInput').focus();
    });

    document.getElementById('amountTenderedInput').addEventListener('input', (e) => {
        const tendered = parseFloat(e.target.value) || 0;
        const change = round2(tendered - cartTotalForPayment);
        document.getElementById('paymentChange').textContent = '₱' + (change > 0 ? change.toFixed(2) : '0.00');
    });

    document.getElementById('amountTenderedInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('confirmPaymentBtn').click();
        }
    });

    document.getElementById('confirmPaymentBtn').addEventListener('click', async () => {
        const tendered = round2(parseFloat(document.getElementById('amountTenderedInput').value) || 0);

        if (tendered < cartTotalForPayment) {
            Swal.fire('Insufficient Amount', 'Cash tendered must be at least the total due', 'warning');
            return;
        }

        try {
            // Snapshot the cart before clearing it — the receipt is built from
            // this, since the checkout response itself doesn't echo the lines back.
            const cartSnapshot = cart.map(item => ({ ...item }));
            const response = await axios.post('/api/purchases', { items: cart, amount_tendered: tendered });

            cart = [];
            updateCart();
            loadItems();

            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            showReceipt(response.data.transaction_id, cartSnapshot, response.data.amount_tendered, response.data.change_amount);
        } catch (error) {
            console.error('Checkout error:', error);
            Swal.fire('Error', error.response?.data?.message || 'Checkout failed', 'error');
        }
    });

    document.getElementById('paymentModal').addEventListener('hidden.bs.modal', focusBarcodeInput);

    // Receipt — built the same VAT-breakdown way app/Models/Invoice.php's
    // Sales Invoice already computes (see InvoiceController::store()): a
    // line is Vatable if its product has a tax assigned, otherwise
    // Vat-Exempt; VAT uses the currently active rate from the taxes table,
    // applied on top of the (VAT-exclusive) line amounts. A walk-in sale has
    // no OSCA/PWD id or withholding tax, so — matching the Invoice's own
    // "no OSCA" branch — there's no VAT/SC deduction: Amount Due is simply
    // the VAT-inclusive total.
    const ACTIVE_VAT_RATE = {{ $activeVatRate }};
    const CASHIER_NAME = @json(Auth::user()->name);
    const COMPANY_NAME = @json(config('company.name'));
    const COMPANY_ADDRESS = @json(config('company.address'));
    const COMPANY_TIN = @json(config('company.tin'));

    function round2(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function computeVatBreakdown(cartSnapshot) {
        let vatSales = 0;
        let vatexSales = 0;

        cartSnapshot.forEach(item => {
            if (item.taxable) {
                vatSales += item.total_price;
            } else {
                vatexSales += item.total_price;
            }
        });

        vatSales = round2(vatSales);
        vatexSales = round2(vatexSales);
        const vatAmount = round2(vatSales * (ACTIVE_VAT_RATE / 100));
        const totalSales = round2(vatSales + vatexSales + vatAmount);

        return { vatSales, vatexSales, vatAmount, totalSales, amountDue: totalSales };
    }

    function showReceipt(transactionId, cartSnapshot, amountTendered, changeAmount) {
        const b = computeVatBreakdown(cartSnapshot);
        const now = new Date();

        const itemsHtml = cartSnapshot.map(item =>
            '<tr>' +
            '<td>' + item.quantity + '</td>' +
            '<td>' + item.item_name + '</td>' +
            '<td class="text-end">' + item.unit_price.toFixed(2) + '</td>' +
            '<td class="text-end">' + item.total_price.toFixed(2) + '</td>' +
            '</tr>'
        ).join('');

        document.getElementById('receiptContent').innerHTML =
            '<div class="text-center mb-2">' +
                '<div class="fw-bold">' + COMPANY_NAME + '</div>' +
                '<div class="small">' + COMPANY_ADDRESS + '</div>' +
                '<div class="small">VAT Reg TIN: ' + COMPANY_TIN + '</div>' +
            '</div>' +
            '<div class="text-center mb-2">' +
                '<div class="fw-bold">SALES RECEIPT</div>' +
                '<div class="small">Txn: ' + transactionId + '</div>' +
                '<div class="small">' + now.toLocaleString() + '</div>' +
                '<div class="small">Cashier: ' + CASHIER_NAME + '</div>' +
            '</div>' +
            '<hr>' +
            '<table class="table table-sm receipt-items-table mb-2">' +
                '<thead><tr><th>Qty</th><th>Item</th><th class="text-end">Price</th><th class="text-end">Amount</th></tr></thead>' +
                '<tbody>' + itemsHtml + '</tbody>' +
            '</table>' +
            '<hr>' +
            '<table class="table table-sm receipt-totals mb-0">' +
                '<tbody>' +
                    '<tr><td>Vatable Sales</td><td class="text-end">' + b.vatSales.toFixed(2) + '</td></tr>' +
                    '<tr><td>Vat-Exempt Sales</td><td class="text-end">' + b.vatexSales.toFixed(2) + '</td></tr>' +
                    '<tr><td>Vat Amount</td><td class="text-end">' + b.vatAmount.toFixed(2) + '</td></tr>' +
                    '<tr class="fw-bold border-top"><td>Total Sales (Vat Inclusive)</td><td class="text-end">' + b.totalSales.toFixed(2) + '</td></tr>' +
                    '<tr class="fw-bold fs-5"><td>Amount Due</td><td class="text-end">₱' + b.amountDue.toFixed(2) + '</td></tr>' +
                    '<tr><td>Cash Tendered</td><td class="text-end">₱' + parseFloat(amountTendered).toFixed(2) + '</td></tr>' +
                    '<tr class="fw-bold"><td>Change</td><td class="text-end">₱' + parseFloat(changeAmount).toFixed(2) + '</td></tr>' +
                '</tbody>' +
            '</table>' +
            '<div class="text-center small mt-3">Thank you for your purchase!</div>';

        new bootstrap.Modal(document.getElementById('receiptModal')).show();
    }

    document.getElementById('printReceiptBtn').addEventListener('click', () => {
        window.print();
    });

    document.getElementById('receiptModal').addEventListener('hidden.bs.modal', focusBarcodeInput);

    // Load items on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadItems();
        focusBarcodeInput();
    });
</script>

<style>
    .items-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }
    .items-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }
    .items-card:active {
        transform: translateY(-2px);
    }
    .btn-group .btn {
        border-radius: 0.25rem;
    }

    .receipt-sheet {
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
    }
    .receipt-items-table th,
    .receipt-items-table td,
    .receipt-totals td {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
        border-color: #ccc;
    }

    @media print {
        body * {
            visibility: hidden;
        }
        #receiptModal, #receiptModal * {
            visibility: visible;
        }
        #receiptModal {
            position: fixed;
            inset: 0;
            background: #fff;
        }
        #receiptModal .modal-dialog {
            max-width: 100%;
            margin: 0;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

@endsection