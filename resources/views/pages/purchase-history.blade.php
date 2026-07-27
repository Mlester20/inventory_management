@extends('layout.user')

@section('title', 'My Purchases')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Sales History</h5>
                    <button class="btn btn-sm btn-primary" onclick="location.reload()">
                        <i class="bx bx-refresh me-1"></i>Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="purchasesTableBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bx bx-loader bx-spin fs-1"></i>
                                    <p>Loading purchases...</p>
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
    async function loadPurchaseHistory() {
        try {
            const response = await axios.get('/api/purchases/history');
            const purchases = response.data.data || response.data;
            
            if (!purchases || purchases.length === 0) {
                document.getElementById('purchasesTableBody').innerHTML = 
                    '<tr><td colspan="7" class="text-center text-muted py-4">No purchases yet</td></tr>';
                return;
            }

            const rows = purchases.map((purchase, index) => {
                return `
                    <tr>
                        <td>${purchase.id}</td>
                        <td>${purchase.product_batch?.product?.item_name || 'N/A'}</td>
                        <td>
                            <span class="badge bg-label-primary">${purchase.product_batch?.product?.category?.category_name || 'N/A'}</span>
                        </td>
                        <td>${purchase.quantity_sold}</td>
                        <td>₱${parseFloat(purchase.unit_price).toFixed(2)}</td>
                        <td><strong>₱${parseFloat(purchase.total_price).toFixed(2)}</strong></td>
                        <td>${new Date(purchase.purchase_date).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        })}</td>
                    </tr>
                `;
            }).join('');

            document.getElementById('purchasesTableBody').innerHTML = rows;

        } catch (error) {
            console.error('Error loading purchases:', error);
            document.getElementById('purchasesTableBody').innerHTML = 
                '<tr><td colspan="7" class="text-center text-danger py-4">Error loading purchases</td></tr>';
        }
    }

    // Load on page load
    document.addEventListener('DOMContentLoaded', loadPurchaseHistory);
</script>
@endsection
