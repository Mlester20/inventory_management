@extends('layout.app')

@section('title', 'Products & Inventory')

@section('content')
    <div class="mt-3">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'general' ? 'active' : '' }}" href="{{ route('inventory-items.index', ['tab' => 'general']) }}">General Item View</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'products' ? 'active' : '' }}" href="{{ route('inventory-items.index', ['tab' => 'products']) }}">Products View</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'batches' ? 'active' : '' }}" href="{{ route('inventory-items.index', ['tab' => 'batches']) }}">Lot/Serial &amp; Expiry View</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('inventory-items.index', ['tab' => 'history']) }}">Product History View</a>
            </li>
        </ul>

        <form method="GET" action="{{ route('inventory-items.index') }}" class="d-flex mb-3" style="max-width: 400px;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="search" class="form-control me-2" placeholder="Search..." value="{{ $search }}">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('inventory-items.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary ms-2">Clear</a>
            @endif
        </form>

        {{-- ============================= GENERAL ITEM TAB ============================= --}}
        @if($tab === 'general')
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#genericItemModal">
                New Generic Item
            </button>

            <div class="card">
                <div class="table-responsive nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>General Item Description</th>
                                <th>Unit</th>
                                <th>VAT Type</th>
                                <th>Products</th>
                                <th>Qty</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($generalItems as $genericName)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $genericName->code }}</td>
                                    <td>{{ $genericName->category->category_name ?? 'N/A' }}</td>
                                    <td>{{ $genericName->generic_name }}</td>
                                    <td>{{ $genericName->unit }}</td>
                                    <td>{{ $genericName->vat_type }}</td>
                                    <td>{{ $genericName->products_count }}</td>
                                    <td>
                                        <a href="{{ route('inventory-items.index', ['tab' => 'products', 'search' => $genericName->generic_name]) }}">
                                            {{ $genericName->on_hand_qty }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item edit-generic-btn"
                                                    data-bs-toggle="modal" data-bs-target="#updateGenericItemModal"
                                                    data-id="{{ $genericName->id }}"
                                                    data-code="{{ $genericName->code }}"
                                                    data-name="{{ $genericName->generic_name }}"
                                                    data-category="{{ $genericName->category_id }}"
                                                    data-unit="{{ $genericName->unit }}"
                                                    data-vat-type="{{ $genericName->vat_type }}">
                                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                                </button>

                                                <div class="dropdown-divider"></div>

                                                <form action="{{ route('generic-names.destroy', $genericName) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Delete this generic item?')">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">No generic items yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($generalItems->hasPages())
                    <div class="card-footer">
                        {{ $generalItems->links() }}
                    </div>
                @endif
            </div>

            <!-- New Generic Item Modal -->
            <div class="modal fade" id="genericItemModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form action="{{ route('generic-names.store') }}" method="POST">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">New Generic Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Code</label>
                                        <input type="text" name="code" class="form-control" placeholder="e.g., 00012" value="{{ $nextGenericCode }}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">-- Select Category --</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Generic Description</label>
                                    <textarea name="generic_name" class="form-control" rows="2" placeholder="e.g., Paracetamol 500mg" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Unit</label>
                                        <input type="text" name="unit" class="form-control" placeholder="e.g. Tablet, Box, Bottle" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">VAT Type</label>
                                        <select name="vat_type" class="form-select" required>
                                            @foreach (\App\Models\GenericName::VAT_TYPES as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Create</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Update Generic Item Modal -->
            <div class="modal fade" id="updateGenericItemModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form id="updateGenericItemForm" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Generic Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Code</label>
                                        <input type="text" name="code" id="update_generic_code" class="form-control" placeholder="e.g., 00012" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category_id" id="update_generic_category_id" class="form-select" required>
                                            <option value="">-- Select Category --</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Generic Description</label>
                                    <textarea name="generic_name" id="update_generic_name" class="form-control" rows="2" placeholder="e.g., Paracetamol 500mg" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Unit</label>
                                        <input type="text" name="unit" id="update_generic_unit" class="form-control" placeholder="e.g. Tablet, Box, Bottle" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">VAT Type</label>
                                        <select name="vat_type" id="update_generic_vat_type" class="form-select" required>
                                            @foreach (\App\Models\GenericName::VAT_TYPES as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ============================= PRODUCTS TAB ============================= --}}
        @if($tab === 'products')
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#productModal">
                New Item
            </button>

            <div class="card">
                <div class="table-responsive nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Item Description</th>
                                <th>Barcode</th>
                                <th>Cost</th>
                                <th>Retail</th>
                                <th>WS</th>
                                <th>Price 1</th>
                                <th>Price 2</th>
                                <th>Price 3</th>
                                <th>Warehouse</th>
                                <th>POS</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                @php
                                    $warehouseQty = (int) ($product->warehouse_qty ?? 0);
                                    $posQty = (int) ($product->pos_qty ?? 0);
                                    $totalQty = (int) ($product->total_qty ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->category->category_name ?? 'N/A' }}</td>
                                    <td>{{ $product->item_name }}</td>
                                    <td>{{ $product->barcode ?? '—' }}</td>
                                    <td>{{ number_format($product->unit_cost ?? 0, 2) }}</td>
                                    <td>{{ number_format($product->unit_price, 2) }}</td>
                                    <td>{{ number_format($product->wholesale_price ?? 0, 2) }}</td>
                                    <td>{{ number_format($product->price_1 ?? 0, 2) }}</td>
                                    <td>{{ number_format($product->price_2 ?? 0, 2) }}</td>
                                    <td>{{ number_format($product->price_3 ?? 0, 2) }}</td>
                                    <td>{{ $warehouseQty }}</td>
                                    <td>{{ $posQty }}</td>
                                    <td>
                                        <a href="{{ route('inventory-items.index', ['tab' => 'batches', 'search' => $product->item_name]) }}">{{ $totalQty }}</a>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="{{ route('inventory-items.index', ['tab' => 'history', 'product_id' => $product->id]) }}" class="dropdown-item">
                                                    <i class="bx bx-history me-1"></i> History
                                                </a>
                                                <button type="button" class="dropdown-item edit-product-btn"
                                                    data-bs-toggle="modal" data-bs-target="#updateProductModal"
                                                    data-id="{{ $product->id }}"
                                                    data-code="{{ $product->code }}"
                                                    data-generic-name-id="{{ $product->generic_name_id }}"
                                                    data-brand-name="{{ $product->brand_name }}"
                                                    data-description="{{ $product->description }}"
                                                    data-barcode="{{ $product->barcode }}"
                                                    data-supplier-id="{{ $product->supplier_id }}"
                                                    data-tax-id="{{ $product->tax_id }}"
                                                    data-unit-cost="{{ $product->unit_cost }}"
                                                    data-unit-price-percent="{{ $product->unit_price_percent }}"
                                                    data-unit-price="{{ $product->unit_price }}"
                                                    data-wholesale-percent="{{ $product->wholesale_percent }}"
                                                    data-wholesale-price="{{ $product->wholesale_price }}"
                                                    data-price-1-percent="{{ $product->price_1_percent }}"
                                                    data-price-1="{{ $product->price_1 }}"
                                                    data-price-2-percent="{{ $product->price_2_percent }}"
                                                    data-price-2="{{ $product->price_2 }}"
                                                    data-price-3-percent="{{ $product->price_3_percent }}"
                                                    data-price-3="{{ $product->price_3 }}"
                                                    data-fda-reg-no="{{ $product->fda_reg_no }}"
                                                    data-fda-reg-exp="{{ $product->fda_reg_exp?->format('Y-m-d') }}"
                                                    data-custom-1="{{ $product->custom_field_1 }}"
                                                    data-custom-2="{{ $product->custom_field_2 }}"
                                                    data-custom-3="{{ $product->custom_field_3 }}"
                                                    data-custom-4="{{ $product->custom_field_4 }}"
                                                    data-location="{{ $product->location }}"
                                                    data-threshold="{{ $product->low_stock_threshold }}">
                                                    <i class="bx bx-edit-alt me-1"></i> Edit
                                                </button>

                                                <div class="dropdown-divider"></div>

                                                <form action="{{ route('products.destroy', $product) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Delete this product?')">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="15" class="text-center text-muted py-4">No products yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($products->hasPages())
                    <div class="card-footer">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>

            @include('admin.inventory-items.partials.product-modal', ['mode' => 'create', 'nextProductCode' => $nextProductCode])
            @include('admin.inventory-items.partials.product-modal', ['mode' => 'update'])
        @endif

        {{-- ============================= LOT/SERIAL & EXPIRY TAB ============================= --}}
        @if($tab === 'batches')
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('inventory-adjustments.create') }}" class="btn btn-primary">Adjust Inventory</a>
                <a href="{{ route('inventory-items.index', array_merge(request()->query(), ['tab' => 'batches', 'show_zero' => $showZero ? 0 : 1])) }}" class="btn btn-outline-secondary btn-sm">
                    @if($showZero)
                        <i class="bx bx-hide"></i> Hide zero-qty batches
                    @else
                        <i class="bx bx-show"></i> Show zero-qty batches
                    @endif
                </a>
            </div>

            <div class="card">
                <div class="table-responsive nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Item Description</th>
                                <th>Lot/Batch/Serial</th>
                                <th>Expiry</th>
                                <th>Warehouse</th>
                                <th>POS</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batches as $batch)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $batch->product->code }}</td>
                                    <td>{{ $batch->product->category->category_name ?? 'N/A' }}</td>
                                    <td>{{ $batch->product->item_name }}</td>
                                    <td>{{ $batch->batch_no ?? '—' }}</td>
                                    <td>{{ $batch->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $batch->qtyAtLocation($warehouseId) }}</td>
                                    <td>{{ $batch->qtyAtLocation($posId) }}</td>
                                    <td>{{ $batch->total_qty }}</td>
                                    <td>
                                        <a href="{{ route('inventory-items.index', ['tab' => 'history', 'product_id' => $batch->product_id]) }}" class="btn btn-sm btn-outline-secondary" title="View Product History">
                                            <i class="bx bx-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted py-4">No batches found. Try a different search.</td></tr>
                            @endforelse
                        </tbody>
                        @if($batchTotals)
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="6" class="text-end">Grand Total</td>
                                    <td>{{ $batchTotals->warehouse_qty }}</td>
                                    <td>{{ $batchTotals->pos_qty }}</td>
                                    <td>{{ $batchTotals->total_qty }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @if($batches->hasPages())
                    <div class="card-footer">
                        {{ $batches->links() }}
                    </div>
                @endif
            </div>
        @endif

        {{-- ============================= PRODUCT HISTORY TAB ============================= --}}
        @if($tab === 'history')
            @if($historyProduct)
                <div class="alert alert-secondary py-2 mb-3">
                    Showing history for <strong>{{ $historyProduct->item_name }}</strong> ({{ $historyProduct->code }})
                </div>
            @else
                <div class="alert alert-info py-2 mb-3">
                    Search for a product's name/brand above, or click the <i class="bx bx-history"></i> button on the Products or Lot/Serial &amp; Expiry tab, to view its history.
                </div>
            @endif
            <div class="card">
                <div class="table-responsive nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction</th>
                                <th>Customer</th>
                                <th>Supplier</th>
                                <th>Item Description</th>
                                <th>Lot/Batch/Serial</th>
                                <th>Expiry</th>
                                <th>Location</th>
                                <th>Qty</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!$historyProduct)
                                <tr><td colspan="10" class="text-center text-muted py-4">Search for a product above to view its history.</td></tr>
                            @elseif($history->isEmpty())
                                <tr><td colspan="10" class="text-center text-muted py-4">No movement history for {{ $historyProduct->item_name }} yet.</td></tr>
                            @else
                                @foreach ($history as $movement)
                                    @php
                                        $source = $movement->source;
                                        $transactionLabel = match(true) {
                                            $source instanceof \App\Models\GoodsReceipt => 'Goods Receipt',
                                            $source instanceof \App\Models\DeliveryReceipt => 'Delivery Receipt',
                                            $source instanceof \App\Models\InventoryAdjustment => 'Inventory Adjustment',
                                            $source instanceof \App\Models\Invoice => 'Invoice',
                                            $source instanceof \App\Models\StockTransfer => 'Stock Transfer',
                                            $source instanceof \App\Models\StockDisposal => 'Stock Disposal',
                                            default => $movement->type === 'in' ? 'Stock In' : 'Stock Out',
                                        };
                                        $customer = $source instanceof \App\Models\DeliveryReceipt ? $source->customer?->customer_name : null;
                                        $supplier = $source instanceof \App\Models\GoodsReceipt ? $source->supplier?->supplier_name : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $movement->created_at->format('m/d/Y') }}</td>
                                        <td>{{ $transactionLabel }}</td>
                                        <td>{{ $customer ?? '—' }}</td>
                                        <td>{{ $supplier ?? '—' }}</td>
                                        <td>{{ $historyProduct->item_name }}</td>
                                        <td>{{ $movement->productBatch->batch_no ?? '—' }}</td>
                                        <td>{{ $movement->productBatch->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $movement->location->name ?? '—' }}</td>
                                        <td>{{ $movement->quantity }}</td>
                                        <td>{{ $movement->running_balance }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                @if($historyProduct && $history->hasPages())
                    <div class="card-footer">
                        {{ $history->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.edit-generic-btn').forEach(button => {
        button.addEventListener('click', function () {
            const get = (attr) => this.getAttribute(attr);
            document.getElementById('update_generic_code').value = get('data-code') || '';
            document.getElementById('update_generic_name').value = get('data-name') || '';
            document.getElementById('update_generic_category_id').value = get('data-category') || '';
            document.getElementById('update_generic_unit').value = get('data-unit') || '';
            document.getElementById('update_generic_vat_type').value = get('data-vat-type') || '';

            const form = document.getElementById('updateGenericItemForm');
            form.action = `{{ url('admin/generic-names') }}/${get('data-id')}`;
        });
    });
</script>
@endsection
