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
                            <i class="bx bx-check me-2"></i>Checkout
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

{{-- Quantity Selection Modal --}}
<div class="modal fade" id="quantityModal" tabindex="-1" aria-labelledby="quantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quantityModalLabel">Add to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="modalItemName" class="fw-bold mb-1">Item Name</h6>
                <small class="text-muted" id="availableStock">Available: 0</small>

                <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                    <span class="text-muted">Unit Price:</span>
                    <span class="fw-semibold" id="modalUnitPrice">₱0.00</span>
                </div>

                <div class="d-flex align-items-center gap-2 justify-content-center my-3">
                    <button type="button" class="btn btn-outline-secondary" id="decreaseQty">−</button>
                    <input type="number" id="quantityInput" class="form-control text-center"
                        style="width: 70px;" value="1" min="1">
                    <button type="button" class="btn btn-outline-secondary" id="increaseQty">+</button>
                </div>

                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold">Total:</span>
                    <span class="fw-bold text-primary fs-5" id="modalTotal">₱0.00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary w-100" id="addToCartBtn">
                    <i class="bx bx-cart-add me-1"></i> Add to Cart
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
    let currentItem = null;

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
                (isAvailable ? ' onclick="openQuantityModal(' + item.id + ')"' : '') + '>' +
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

    // Open quantity modal
    function openQuantityModal(itemId) {
        currentItem = items.find(i => i.id === itemId);

        if (!currentItem || currentItem.quantity === 0) {
            Swal.fire('Out of Stock', 'This item is not available', 'warning');
            return;
        }

        document.getElementById('modalItemName').textContent = currentItem.item_name;
        document.getElementById('quantityInput').value = 1;
        document.getElementById('quantityInput').max = currentItem.quantity;
        document.getElementById('availableStock').textContent = 'Available: ' + currentItem.quantity;
        document.getElementById('modalUnitPrice').textContent = '₱' + parseFloat(currentItem.unit_price).toFixed(2);
        updateModalTotal();

        new bootstrap.Modal(document.getElementById('quantityModal')).show();
    }

    // Update modal total
    function updateModalTotal() {
        const qty = parseInt(document.getElementById('quantityInput').value) || 1;
        const total = qty * parseFloat(currentItem?.unit_price || 0);
        document.getElementById('modalTotal').textContent = '₱' + total.toFixed(2);
    }

    document.getElementById('quantityInput').addEventListener('input', updateModalTotal);

    document.getElementById('increaseQty').addEventListener('click', () => {
        const input = document.getElementById('quantityInput');
        if (parseInt(input.value) < parseInt(input.max)) {
            input.value = parseInt(input.value) + 1;
            updateModalTotal();
        }
    });

    document.getElementById('decreaseQty').addEventListener('click', () => {
        const input = document.getElementById('quantityInput');
        if (parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
            updateModalTotal();
        }
    });

    document.getElementById('addToCartBtn').addEventListener('click', () => {
        const quantity = parseInt(document.getElementById('quantityInput').value);

        if (quantity < 1 || quantity > currentItem.quantity) {
            Swal.fire('Invalid Quantity', 'Only ' + currentItem.quantity + ' units available', 'warning');
            return;
        }

        const existing = cart.find(ci => ci.item_id === currentItem.id);
        if (existing) {
            const newQty = existing.quantity + quantity;
            if (newQty > currentItem.quantity) {
                Swal.fire('Stock Limit', 'Only ' + currentItem.quantity + ' units available in total', 'warning');
                return;
            }
            existing.quantity = newQty;
            existing.total_price = newQty * existing.unit_price;
        } else {
            cart.push({
                id: Date.now(),
                item_id: currentItem.id,
                item_name: currentItem.item_name,
                unit_price: parseFloat(currentItem.unit_price),
                quantity: quantity,
                total_price: quantity * parseFloat(currentItem.unit_price),
                max_stock: currentItem.quantity,
            });
        }

        updateCart();

        bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide();

        Swal.fire({
            icon: 'success',
            title: 'Added to Cart',
            text: currentItem.item_name + ' (x' + quantity + ')',
            timer: 1200,
            showConfirmButton: false,
        });
    });

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
                '<button class="btn btn-outline-secondary btn-sm disabled" style="width:46px;">' + item.quantity + '</button>' +
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

    // Search items
    document.getElementById('searchItems').addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        renderItems(items.filter(i =>
            i.item_name.toLowerCase().includes(term) ||
            i.category.category_name.toLowerCase().includes(term)
        ));
    });

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

    // Checkout
    document.getElementById('checkoutBtn').addEventListener('click', async () => {
        if (cart.length === 0) {
            Swal.fire('Empty Cart', 'Please add items before checkout', 'warning');
            return;
        }

        try {
            const response = await axios.post('/api/purchases', { items: cart });

            Swal.fire({
                icon: 'success',
                title: 'Purchase Successful!',
                text: 'Transaction ID: ' + response.data.transaction_id,
                timer: 3000,
            });

            cart = [];
            updateCart();
            loadItems();
        } catch (error) {
            console.error('Checkout error:', error);
            Swal.fire('Error', error.response?.data?.message || 'Checkout failed', 'error');
        }
    });

    // Load items on page load
    document.addEventListener('DOMContentLoaded', loadItems);
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
</style>

@endsection