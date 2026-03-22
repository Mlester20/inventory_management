@extends('layout.user')

@section('title', 'Return Items')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <!-- Return Form Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title m-0">Submit Return Request</h5>
                </div>
                <div class="card-body">
                    <form id="returnItemForm">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="item_id" class="form-label">Select Item to Return <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_id" name="item_id" required>
                                <option value="">-- Choose Item --</option>
                            </select>
                            <div class="form-text" id="itemHelp"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity to Return <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                    <small class="form-text text-muted" id="maxQuantity"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="return_date" name="return_date" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Return Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">-- Select Reason --</option>
                                <option value="Defective Product">Defective Product</option>
                                <option value="Wrong Item Received">Wrong Item Received</option>
                                <option value="Not as Described">Not as Described</option>
                                <option value="Changed Mind">Changed Mind</option>
                                <option value="Damaged During Delivery">Damaged During Delivery</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any additional details..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bx bx-check me-1"></i>Submit Return Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Purchases Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title m-0">Your Purchases (Eligible for Return)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="purchasesTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bx bx-loader bx-spin"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Return History Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Your Return Requests</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadReturnHistory()">
                        <i class="bx bx-refresh"></i>Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Reason</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="returnHistoryTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bx bx-loader bx-spin"></i> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Set today as default return date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('return_date').value = today;

    // Sample purchased items data structure
    let purchasedItems = [];

    // Load purchases on page load
    async function loadPurchases() {
        try {
            const response = await axios.get('/api/purchases/history');
            purchasedItems = response.data.data || response.data || [];

            // Populate the item dropdown
            const itemSelect = document.getElementById('item_id');
            itemSelect.innerHTML = '<option value="">-- Choose Item --</option>';

            purchasedItems.forEach(purchase => {
                const option = document.createElement('option');
                option.value = purchase.item_id;
                option.textContent = `${purchase.item?.item_name || 'Unknown'} - ₱${parseFloat(purchase.unit_price).toFixed(2)}`;
                option.dataset.maxQty = purchase.quantity_sold;
                option.dataset.itemName = purchase.item?.item_name || 'Unknown';
                itemSelect.appendChild(option);
            });

            // Populate the purchases table
            const rows = purchasedItems.map(purchase => `
                <tr>
                    <td>
                        <small>${purchase.item?.item_name || 'N/A'}</small>
                    </td>
                    <td>${purchase.quantity_sold}</td>
                    <td>₱${parseFloat(purchase.unit_price).toFixed(2)}</td>
                    <td><small>${new Date(purchase.purchase_date).toLocaleDateString()}</small></td>
                </tr>
            `).join('');

            document.getElementById('purchasesTableBody').innerHTML = rows || 
                '<tr><td colspan="4" class="text-center text-muted">No purchases found</td></tr>';

        } catch (error) {
            console.error('Error loading purchases:', error);
            document.getElementById('purchasesTableBody').innerHTML = 
                '<tr><td colspan="4" class="text-center text-danger">Error loading purchases</td></tr>';
        }
    }

    // Update max quantity and item info when item is selected
    document.getElementById('item_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const maxQty = selectedOption.dataset.maxQty;
        const itemName = selectedOption.dataset.itemName;

        if (selectedOption.value) {
            document.getElementById('maxQuantity').textContent = `Max quantity: ${maxQty}`;
            document.getElementById('quantity').max = maxQty;
            document.getElementById('quantity').value = 1;
        } else {
            document.getElementById('maxQuantity').textContent = '';
            document.getElementById('quantity').value = '';
            document.getElementById('quantity').max = '';
        }
    });

    // Load return history
    async function loadReturnHistory() {
        try {
            const response = await axios.get('/api/return-items');
            const returns = response.data.data || response.data || [];

            if (returns.length === 0) {
                document.getElementById('returnHistoryTableBody').innerHTML = 
                    '<tr><td colspan="6" class="text-center text-muted py-4">No return requests yet</td></tr>';
                return;
            }

            const rows = returns.map(returnItem => {
                const statusBadge = `
                    <span class="badge bg-${
                        returnItem.status === 'approved' ? 'success' :
                        returnItem.status === 'rejected' ? 'danger' :
                        'warning'
                    }">
                        ${returnItem.status.charAt(0).toUpperCase() + returnItem.status.slice(1)}
                    </span>
                `;

                return `
                    <tr>
                        <td>${returnItem.id}</td>
                        <td>${returnItem.item?.item_name || 'N/A'}</td>
                        <td>${returnItem.quantity}</td>
                        <td><small>${returnItem.reason}</small></td>
                        <td>${new Date(returnItem.created_at).toLocaleDateString()}</td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
            }).join('');

            document.getElementById('returnHistoryTableBody').innerHTML = rows;

        } catch (error) {
            console.error('Error loading return history:', error);
            document.getElementById('returnHistoryTableBody').innerHTML = 
                '<tr><td colspan="6" class="text-center text-danger py-4">Error loading returns</td></tr>';
        }
    }

    // Handle form submission
    document.getElementById('returnItemForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = {
            item_id: document.getElementById('item_id').value,
            quantity: parseInt(document.getElementById('quantity').value),
            return_date: document.getElementById('return_date').value,
            reason: document.getElementById('reason').value,
            notes: document.getElementById('notes').value
        };

        // Validate quantity
        const selectedOption = document.getElementById('item_id').options[document.getElementById('item_id').selectedIndex];
        const maxQty = parseInt(selectedOption.dataset.maxQty);

        if (formData.quantity > maxQty) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Quantity',
                text: `You can only return up to ${maxQty} units of this item.`
            });
            return;
        }

        try {
            const response = await axios.post('/api/return-items', formData);

            Swal.fire({
                icon: 'success',
                title: 'Return Request Submitted',
                text: 'Your return request has been submitted successfully. Status: Pending',
                confirmButtonText: 'OK'
            }).then(() => {
                document.getElementById('returnItemForm').reset();
                document.getElementById('return_date').value = today;
                loadReturnHistory();
            });

        } catch (error) {
            console.error('Error submitting return:', error);
            let errorMessage = 'An error occurred while submitting your return request.';

            if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            } else if (error.response?.data?.errors) {
                errorMessage = Object.values(error.response.data.errors).flat().join(', ');
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        }
    });

    // Load data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadPurchases();
        loadReturnHistory();
    });
</script>
@endsection