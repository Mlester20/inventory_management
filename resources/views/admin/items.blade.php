@extends('layout.app')

@section('title', 'Categories')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#itemModal"
        >
            Add Item
        </button>

        <!-- Add Item Modal -->
        <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('items.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Item</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="item_name" class="form-label">
                                    Item Name
                                </label>
                                <input
                                    type="text"
                                    name="item_name"
                                    id="item_name"
                                    class="form-control"
                                    placeholder="Enter item name"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">
                                    Category
                                </label>
                                <select
                                    name="category_id"
                                    id="category_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="supplier_id" class="form-label">
                                    Supplier
                                </label>
                                <select
                                    name="supplier_id"
                                    id="supplier_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">
                                            {{ $supplier->supplier_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    Description
                                </label>
                                <textarea
                                    name="description"
                                    id="description"
                                    class="form-control"
                                    placeholder="Enter description"
                                    rows="2"
                                ></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="quantity" class="form-label">
                                        Quantity
                                    </label>
                                    <input
                                        type="number"
                                        name="quantity"
                                        id="quantity"
                                        class="form-control"
                                        placeholder="Enter quantity"
                                        required
                                    >
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="unit_price" class="form-label">
                                        Unit Price
                                    </label>
                                    <input
                                        type="number"
                                        name="unit_price"
                                        id="unit_price"
                                        class="form-control"
                                        placeholder="Enter unit price"
                                        step="0.01"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="low_stock_threshold" class="form-label">
                                    Low Stock Threshold
                                </label>
                                <input
                                    type="number"
                                    name="low_stock_threshold"
                                    id="low_stock_threshold"
                                    class="form-control"
                                    placeholder="Enter low stock threshold"
                                    required
                                >
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Update Item Modal -->
        <div class="modal fade" id="updateItemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateItemForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Item</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_item_name" class="form-label">
                                    Item Name
                                </label>
                                <input
                                    type="text"
                                    name="item_name"
                                    id="update_item_name"
                                    class="form-control"
                                    placeholder="Enter item name"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="update_category_id" class="form-label">
                                    Category
                                </label>
                                <select
                                    name="category_id"
                                    id="update_category_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="update_supplier_id" class="form-label">
                                    Supplier
                                </label>
                                <select
                                    name="supplier_id"
                                    id="update_supplier_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">
                                            {{ $supplier->supplier_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="update_description" class="form-label">
                                    Description
                                </label>
                                <textarea
                                    name="description"
                                    id="update_description"
                                    class="form-control"
                                    placeholder="Enter description"
                                    rows="2"
                                ></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="update_quantity" class="form-label">
                                        Quantity
                                    </label>
                                    <input
                                        type="number"
                                        name="quantity"
                                        id="update_quantity"
                                        class="form-control"
                                        placeholder="Enter quantity"
                                        required
                                    >
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="update_unit_price" class="form-label">
                                        Unit Price
                                    </label>
                                    <input
                                        type="number"
                                        name="unit_price"
                                        id="update_unit_price"
                                        class="form-control"
                                        placeholder="Enter unit price"
                                        step="0.01"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="update_low_stock_threshold" class="form-label">
                                    Low Stock Threshold
                                </label>
                                <input
                                    type="number"
                                    name="low_stock_threshold"
                                    id="update_low_stock_threshold"
                                    class="form-control"
                                    placeholder="Enter low stock threshold"
                                    required
                                >
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Item Modal -->
        <div class="modal fade" id="viewItemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Item Details</h5>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>ID:</strong></label>
                                <p id="view_item_id" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Item Name:</strong></label>
                                <p id="view_item_name" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Category:</strong></label>
                                <p id="view_category" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Supplier:</strong></label>
                                <p id="view_supplier" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label><strong>Description:</strong></label>
                                <p id="view_description" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label><strong>Quantity:</strong></label>
                                <p id="view_quantity" class="text-muted"></p>
                            </div>
                            <div class="col-md-4">
                                <label><strong>Unit Price:</strong></label>
                                <p id="view_price" class="text-muted"></p>
                            </div>
                            <div class="col-md-4">
                                <label><strong>Low Stock Threshold:</strong></label>
                                <p id="view_threshold" class="text-muted"></p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Suppliers</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Supplier Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->item_name }}</td>
                            <td>{{ $item->category->category_name }}</td>
                            <td>{{ $item->supplier->supplier_name }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewItemModal"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->item_name }}"
                                    data-category="{{ $item->category->category_name }}"
                                    data-supplier="{{ $item->supplier->supplier_name }}"
                                    data-description="{{ $item->description }}"
                                    data-quantity="{{ $item->quantity }}"
                                    data-price="{{ $item->unit_price }}"
                                    data-threshold="{{ $item->low_stock_threshold }}"
                                >
                                    View
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateItemModal"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->item_name }}"
                                    data-category="{{ $item->category_id }}"
                                    data-supplier="{{ $item->supplier_id }}"
                                    data-description="{{ $item->description }}"
                                    data-quantity="{{ $item->quantity }}"
                                    data-price="{{ $item->unit_price }}"
                                    data-threshold="{{ $item->low_stock_threshold }}"
                                >
                                    Edit
                                </button>

                                <form
                                    action="{{ route('items.destroy', $item) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this item?')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Handle view button click to populate the view modal
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');
            const category = this.getAttribute('data-category');
            const supplier = this.getAttribute('data-supplier');
            const description = this.getAttribute('data-description');
            const quantity = this.getAttribute('data-quantity');
            const price = this.getAttribute('data-price');
            const threshold = this.getAttribute('data-threshold');
            
            // Populate the view modal
            document.getElementById('view_item_id').textContent = itemId;
            document.getElementById('view_item_name').textContent = itemName;
            document.getElementById('view_category').textContent = category;
            document.getElementById('view_supplier').textContent = supplier;
            document.getElementById('view_description').textContent = description || 'N/A';
            document.getElementById('view_quantity').textContent = quantity;
            document.getElementById('view_price').textContent = '$' + parseFloat(price).toFixed(2);
            document.getElementById('view_threshold').textContent = threshold;
        });
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');
            const categoryId = this.getAttribute('data-category');
            const supplierId = this.getAttribute('data-supplier');
            const description = this.getAttribute('data-description');
            const quantity = this.getAttribute('data-quantity');
            const price = this.getAttribute('data-price');
            const threshold = this.getAttribute('data-threshold');
            
            // Populate the modal form
            document.getElementById('update_item_name').value = itemName;
            document.getElementById('update_category_id').value = categoryId;
            document.getElementById('update_supplier_id').value = supplierId;
            document.getElementById('update_description').value = description;
            document.getElementById('update_quantity').value = quantity;
            document.getElementById('update_unit_price').value = price;
            document.getElementById('update_low_stock_threshold').value = threshold;
            
            // Set the form action to the update route
            const form = document.getElementById('updateItemForm');
            form.action = `{{ route('items.index') }}/${itemId}`;
        });
    });

    // Clear the add item form when the modal is hidden
    const itemModal = document.getElementById('itemModal');
    itemModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('item_name').value = '';
        document.getElementById('category_id').value = '';
        document.getElementById('supplier_id').value = '';
        document.getElementById('description').value = '';
        document.getElementById('quantity').value = '';
        document.getElementById('unit_price').value = '';
        document.getElementById('low_stock_threshold').value = '';
    });
</script>
@endsection