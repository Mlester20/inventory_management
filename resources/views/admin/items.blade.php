@extends('layout.app')

@section('title', 'Products')

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

        <!-- Filter Button -->
        @if($showLowStockOnly)
            <a href="{{ route('items.index') }}" class="btn btn-warning">
                <i class="fas fa-times"></i> Clear Filter
            </a>
            <span class="badge bg-warning text-dark ms-2">Showing Low Stock Items</span>
        @else
            <a href="{{ route('items.index', ['filter' => 'low-stock']) }}" class="btn btn-danger">
                <i class="fas fa-exclamation-triangle"></i> Low Stock Items
            </a>
        @endif

        <!-- Add Item Modal -->
        <div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">

                <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data">
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
                                <label for="generic_name_id" class="form-label">
                                    Generic Name
                                </label>
                                <select
                                    name="generic_name_id"
                                    id="generic_name_id"
                                    class="form-select @error('generic_name_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Generic Name --</option>
                                    @foreach ($genericNames as $genericName)
                                        <option
                                            value="{{ $genericName->id }}"
                                            data-category="{{ $genericName->category->category_name }}"
                                            data-unit="{{ $genericName->unit }}"
                                            {{ old('generic_name_id') == $genericName->id ? 'selected' : '' }}
                                        >
                                            {{ $genericName->generic_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('generic_name_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" id="generic_name_category_display" class="form-control" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" id="generic_name_unit_display" class="form-control" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="brand_name" class="form-label">
                                    Brand Name
                                </label>
                                <input
                                    type="text"
                                    name="brand_name"
                                    id="brand_name"
                                    class="form-control @error('brand_name') is-invalid @enderror"
                                    placeholder="Enter brand name"
                                    value="{{ old('brand_name') }}"
                                    required
                                >
                                @error('brand_name')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="serial_number" class="form-label">
                                    Serial Number
                                </label>
                                <input
                                    type="text"
                                    name="serial_number"
                                    id="serial_number"
                                    class="form-control @error('serial_number') is-invalid @enderror"
                                    placeholder="Enter serial number (optional)"
                                    value="{{ old('serial_number') }}"
                                >
                                @error('serial_number')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="supplier_id" class="form-label">
                                    Supplier
                                </label>
                                <select
                                    name="supplier_id"
                                    id="supplier_id"
                                    class="form-select @error('supplier_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->supplier_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="tax_id" class="form-label">
                                    Tax / VAT Classification
                                </label>
                                <select
                                    name="tax_id"
                                    id="tax_id"
                                    class="form-select @error('tax_id') is-invalid @enderror"
                                >
                                    <option value="">-- No Tax --</option>
                                    @foreach ($taxes as $tax)
                                        <option value="{{ $tax->id }}" {{ old('tax_id') == $tax->id ? 'selected' : '' }}>
                                            {{ $tax->name }} ({{ $tax->rate }}%){{ !$tax->is_active ? ' — Inactive' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tax_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">
                                    Item Image
                                </label>
                                <input
                                    type="file"
                                    name="image"
                                    id="image"
                                    class="form-control @error('image') is-invalid @enderror"
                                    accept="image/png,image/jpeg,image/webp"
                                >
                                <div class="form-text">JPG, PNG or WEBP, up to 2MB.</div>
                                @error('image')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    Description
                                </label>
                                <textarea
                                    name="description"
                                    id="description"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Enter description"
                                    rows="2"
                                >{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
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
                                        class="form-control @error('quantity') is-invalid @enderror"
                                        placeholder="Enter quantity"
                                        value="{{ old('quantity') }}"
                                        required
                                    >
                                    @error('quantity')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="unit_cost" class="form-label">
                                        Unit Cost
                                    </label>
                                    <input
                                        type="number"
                                        name="unit_cost"
                                        id="unit_cost"
                                        class="form-control @error('unit_cost') is-invalid @enderror"
                                        placeholder="Enter unit cost"
                                        step="0.01"
                                        value="{{ old('unit_cost') }}"
                                        required
                                    >
                                    @error('unit_cost')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
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
                                    class="form-control @error('low_stock_threshold') is-invalid @enderror"
                                    placeholder="Enter low stock threshold"
                                    value="{{ old('low_stock_threshold') }}"
                                    required
                                >
                                @error('low_stock_threshold')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <hr>
                            <h6 class="mb-3">Unit Price</h6>

                            <div class="mb-3">
                                <label for="unit_price" class="form-label">
                                    Retail Price
                                </label>
                                <input
                                    type="number"
                                    name="unit_price"
                                    id="unit_price"
                                    class="form-control @error('unit_price') is-invalid @enderror"
                                    placeholder="Enter retail price"
                                    step="0.01"
                                    value="{{ old('unit_price') }}"
                                    required
                                >
                                @error('unit_price')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            @foreach ([
                                ['label' => 'Wholesale Price', 'percent' => 'wholesale_percent', 'value' => 'wholesale_price'],
                                ['label' => 'Price 1', 'percent' => 'price_1_percent', 'value' => 'price_1'],
                                ['label' => 'Price 2', 'percent' => 'price_2_percent', 'value' => 'price_2'],
                                ['label' => 'Price 3', 'percent' => 'price_3_percent', 'value' => 'price_3'],
                            ] as $tier)
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label">{{ $tier['label'] }}</label>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="{{ $tier['percent'] }}"
                                                id="{{ $tier['percent'] }}"
                                                class="form-control @error($tier['percent']) is-invalid @enderror"
                                                placeholder="%"
                                                step="0.01"
                                                value="{{ old($tier['percent']) }}"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <input
                                            type="number"
                                            name="{{ $tier['value'] }}"
                                            id="{{ $tier['value'] }}"
                                            class="form-control @error($tier['value']) is-invalid @enderror"
                                            placeholder="Computed price (editable)"
                                            step="0.01"
                                            value="{{ old($tier['value']) }}"
                                        >
                                    </div>
                                </div>
                            @endforeach

                            <hr>

                            <div class="mb-3">
                                <label for="location" class="form-label">
                                    Location
                                </label>
                                <input
                                    type="text"
                                    name="location"
                                    id="location"
                                    class="form-control @error('location') is-invalid @enderror"
                                    placeholder="Enter stock location (optional)"
                                    value="{{ old('location') }}"
                                >
                                @error('location')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="batch_no" class="form-label">
                                        Batch No.
                                    </label>
                                    <input
                                        type="text"
                                        name="batch_no"
                                        id="batch_no"
                                        class="form-control @error('batch_no') is-invalid @enderror"
                                        placeholder="Enter batch number (optional)"
                                        value="{{ old('batch_no') }}"
                                    >
                                    @error('batch_no')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="expiration_date" class="form-label">
                                        Expiration Date
                                    </label>
                                    <input
                                        type="date"
                                        name="expiration_date"
                                        id="expiration_date"
                                        class="form-control @error('expiration_date') is-invalid @enderror"
                                        value="{{ old('expiration_date') }}"
                                    >
                                    @error('expiration_date')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
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
            <div class="modal-dialog modal-lg" role="document">

                <form id="updateItemForm" method="POST" enctype="multipart/form-data">
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
                                <label for="update_generic_name_id" class="form-label">
                                    Generic Name
                                </label>
                                <select
                                    name="generic_name_id"
                                    id="update_generic_name_id"
                                    class="form-select @error('generic_name_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Generic Name --</option>
                                    @foreach ($genericNames as $genericName)
                                        <option
                                            value="{{ $genericName->id }}"
                                            data-category="{{ $genericName->category->category_name }}"
                                            data-unit="{{ $genericName->unit }}"
                                        >
                                            {{ $genericName->generic_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('generic_name_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" id="update_generic_name_category_display" class="form-control" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" id="update_generic_name_unit_display" class="form-control" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="update_brand_name" class="form-label">
                                    Brand Name
                                </label>
                                <input
                                    type="text"
                                    name="brand_name"
                                    id="update_brand_name"
                                    class="form-control @error('brand_name') is-invalid @enderror"
                                    placeholder="Enter brand name"
                                    required
                                >
                                @error('brand_name')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_serial_number" class="form-label">
                                    Serial Number
                                </label>
                                <input
                                    type="text"
                                    name="serial_number"
                                    id="update_serial_number"
                                    class="form-control @error('serial_number') is-invalid @enderror"
                                    placeholder="Enter serial number (optional)"
                                >
                                @error('serial_number')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_supplier_id" class="form-label">
                                    Supplier
                                </label>
                                <select
                                    name="supplier_id"
                                    id="update_supplier_id"
                                    class="form-select @error('supplier_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">
                                            {{ $supplier->supplier_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_tax_id" class="form-label">
                                    Tax / VAT Classification
                                </label>
                                <select
                                    name="tax_id"
                                    id="update_tax_id"
                                    class="form-select @error('tax_id') is-invalid @enderror"
                                >
                                    <option value="">-- No Tax --</option>
                                    @foreach ($taxes as $tax)
                                        <option value="{{ $tax->id }}">
                                            {{ $tax->name }} ({{ $tax->rate }}%){{ !$tax->is_active ? ' — Inactive' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tax_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_image" class="form-label">
                                    Item Image
                                </label>
                                <div class="mb-2">
                                    <img
                                        id="update_image_preview"
                                        src=""
                                        alt="Current item image"
                                        class="img-thumbnail d-none"
                                        style="max-height: 100px;"
                                    >
                                    <p id="update_image_none" class="text-muted small mb-0">No image uploaded yet.</p>
                                </div>
                                <input
                                    type="file"
                                    name="image"
                                    id="update_image"
                                    class="form-control @error('image') is-invalid @enderror"
                                    accept="image/png,image/jpeg,image/webp"
                                >
                                <div class="form-text">Uploading a new image replaces the current one. JPG, PNG or WEBP, up to 2MB.</div>
                                @error('image')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_description" class="form-label">
                                    Description
                                </label>
                                <textarea
                                    name="description"
                                    id="update_description"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Enter description"
                                    rows="2"
                                ></textarea>
                                @error('description')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
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
                                        class="form-control @error('quantity') is-invalid @enderror"
                                        placeholder="Enter quantity"
                                        required
                                    >
                                    @error('quantity')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="update_unit_cost" class="form-label">
                                        Unit Cost
                                    </label>
                                    <input
                                        type="number"
                                        name="unit_cost"
                                        id="update_unit_cost"
                                        class="form-control @error('unit_cost') is-invalid @enderror"
                                        placeholder="Enter unit cost"
                                        step="0.01"
                                        required
                                    >
                                    @error('unit_cost')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
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
                                    class="form-control @error('low_stock_threshold') is-invalid @enderror"
                                    placeholder="Enter low stock threshold"
                                    required
                                >
                                @error('low_stock_threshold')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <hr>
                            <h6 class="mb-3">Unit Price</h6>

                            <div class="mb-3">
                                <label for="update_unit_price" class="form-label">
                                    Retail Price
                                </label>
                                <input
                                    type="number"
                                    name="unit_price"
                                    id="update_unit_price"
                                    class="form-control @error('unit_price') is-invalid @enderror"
                                    placeholder="Enter retail price"
                                    step="0.01"
                                    required
                                >
                                @error('unit_price')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            @foreach ([
                                ['label' => 'Wholesale Price', 'percent' => 'wholesale_percent', 'value' => 'wholesale_price'],
                                ['label' => 'Price 1', 'percent' => 'price_1_percent', 'value' => 'price_1'],
                                ['label' => 'Price 2', 'percent' => 'price_2_percent', 'value' => 'price_2'],
                                ['label' => 'Price 3', 'percent' => 'price_3_percent', 'value' => 'price_3'],
                            ] as $tier)
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label">{{ $tier['label'] }}</label>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="{{ $tier['percent'] }}"
                                                id="update_{{ $tier['percent'] }}"
                                                class="form-control @error($tier['percent']) is-invalid @enderror"
                                                placeholder="%"
                                                step="0.01"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <input
                                            type="number"
                                            name="{{ $tier['value'] }}"
                                            id="update_{{ $tier['value'] }}"
                                            class="form-control @error($tier['value']) is-invalid @enderror"
                                            placeholder="Computed price (editable)"
                                            step="0.01"
                                        >
                                    </div>
                                </div>
                            @endforeach

                            <hr>

                            <div class="mb-3">
                                <label for="update_location" class="form-label">
                                    Location
                                </label>
                                <input
                                    type="text"
                                    name="location"
                                    id="update_location"
                                    class="form-control @error('location') is-invalid @enderror"
                                    placeholder="Enter stock location (optional)"
                                >
                                @error('location')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="update_batch_no" class="form-label">
                                        Batch No.
                                    </label>
                                    <input
                                        type="text"
                                        name="batch_no"
                                        id="update_batch_no"
                                        class="form-control @error('batch_no') is-invalid @enderror"
                                        placeholder="Enter batch number (optional)"
                                    >
                                    @error('batch_no')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="update_expiration_date" class="form-label">
                                        Expiration Date
                                    </label>
                                    <input
                                        type="date"
                                        name="expiration_date"
                                        id="update_expiration_date"
                                        class="form-control @error('expiration_date') is-invalid @enderror"
                                    >
                                    @error('expiration_date')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
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
            <div class="modal-dialog modal-lg" role="document">
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
                            <div class="col-md-12 text-center">
                                <img
                                    id="view_image_preview"
                                    src=""
                                    alt="Item image"
                                    class="img-thumbnail d-none"
                                    style="max-height: 150px;"
                                >
                                <p id="view_image_none" class="text-muted small mb-0">No image uploaded.</p>
                            </div>
                        </div>
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
                                <label><strong>Generic Name:</strong></label>
                                <p id="view_generic_name" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Brand Name:</strong></label>
                                <p id="view_brand_name" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Serial Number:</strong></label>
                                <p id="view_serial_number" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Tax / VAT Classification:</strong></label>
                                <p id="view_tax" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label><strong>Category:</strong></label>
                                <p id="view_category" class="text-muted"></p>
                            </div>
                            <div class="col-md-4">
                                <label><strong>Unit:</strong></label>
                                <p id="view_unit" class="text-muted"></p>
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-3">
                                <label><strong>Quantity:</strong></label>
                                <p id="view_quantity" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Unit Cost:</strong></label>
                                <p id="view_unit_cost" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Low Stock Threshold:</strong></label>
                                <p id="view_threshold" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Location:</strong></label>
                                <p id="view_location" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label><strong>Retail Price:</strong></label>
                                <p id="view_price" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Wholesale:</strong></label>
                                <p id="view_wholesale" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Price 1:</strong></label>
                                <p id="view_price_1" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Price 2:</strong></label>
                                <p id="view_price_2" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label><strong>Price 3:</strong></label>
                                <p id="view_price_3" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Batch No.:</strong></label>
                                <p id="view_batch_no" class="text-muted"></p>
                            </div>
                            <div class="col-md-3">
                                <label><strong>Expiration Date:</strong></label>
                                <p id="view_expiration_date" class="text-muted"></p>
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
        <h5 class="card-header">
            @if($showLowStockOnly)
                Low Stock Items
            @else
                All Items
            @endif
        </h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
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
                            <td>
                                @if($item->image)
                                    <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->item_name }}" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                @else
                                    <i class="bx bx-image text-muted" style="font-size: 1.5rem;"></i>
                                @endif
                            </td>
                            <td>{{ $item->item_name }}</td>
                            <td>{{ $item->genericName->category->category_name ?? 'N/A' }}</td>
                            <td>{{ $item->supplier->supplier_name }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewItemModal"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->item_name }}"
                                    data-generic-name="{{ $item->genericName->generic_name ?? 'N/A' }}"
                                    data-brand-name="{{ $item->brand_name }}"
                                    data-serial="{{ $item->serial_number }}"
                                    data-tax="{{ $item->tax ? $item->tax->name . ' (' . $item->tax->rate . '%)' : 'No Tax' }}"
                                    data-image="{{ $item->image ? asset('storage/' . $item->image) : '' }}"
                                    data-category="{{ $item->genericName->category->category_name ?? 'N/A' }}"
                                    data-unit="{{ $item->genericName->unit ?? 'N/A' }}"
                                    data-supplier="{{ $item->supplier->supplier_name }}"
                                    data-description="{{ $item->description }}"
                                    data-quantity="{{ $item->quantity }}"
                                    data-unit-cost="{{ $item->unit_cost }}"
                                    data-price="{{ $item->unit_price }}"
                                    data-wholesale-percent="{{ $item->wholesale_percent }}"
                                    data-wholesale-price="{{ $item->wholesale_price }}"
                                    data-price-1-percent="{{ $item->price_1_percent }}"
                                    data-price-1="{{ $item->price_1 }}"
                                    data-price-2-percent="{{ $item->price_2_percent }}"
                                    data-price-2="{{ $item->price_2 }}"
                                    data-price-3-percent="{{ $item->price_3_percent }}"
                                    data-price-3="{{ $item->price_3 }}"
                                    data-location="{{ $item->location }}"
                                    data-threshold="{{ $item->low_stock_threshold }}"
                                    data-batch-no="{{ $item->batch_no }}"
                                    data-expiration-date="{{ $item->expiration_date?->format('Y-m-d') }}"
                                >
                                    <i class="bx bx-show"></i>
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateItemModal"
                                    data-id="{{ $item->id }}"
                                    data-generic-name-id="{{ $item->generic_name_id }}"
                                    data-brand-name="{{ $item->brand_name }}"
                                    data-serial="{{ $item->serial_number }}"
                                    data-tax-id="{{ $item->tax_id }}"
                                    data-image="{{ $item->image ? asset('storage/' . $item->image) : '' }}"
                                    data-supplier="{{ $item->supplier_id }}"
                                    data-description="{{ $item->description }}"
                                    data-quantity="{{ $item->quantity }}"
                                    data-unit-cost="{{ $item->unit_cost }}"
                                    data-price="{{ $item->unit_price }}"
                                    data-wholesale-percent="{{ $item->wholesale_percent }}"
                                    data-wholesale-price="{{ $item->wholesale_price }}"
                                    data-price-1-percent="{{ $item->price_1_percent }}"
                                    data-price-1="{{ $item->price_1 }}"
                                    data-price-2-percent="{{ $item->price_2_percent }}"
                                    data-price-2="{{ $item->price_2 }}"
                                    data-price-3-percent="{{ $item->price_3_percent }}"
                                    data-price-3="{{ $item->price_3 }}"
                                    data-location="{{ $item->location }}"
                                    data-threshold="{{ $item->low_stock_threshold }}"
                                    data-batch-no="{{ $item->batch_no }}"
                                    data-expiration-date="{{ $item->expiration_date?->format('Y-m-d') }}"
                                >
                                    <i class="bx bx-edit"></i>
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
                                        <i class="bx bx-trash"></i>
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
    // Auto-populate the read-only Category/Unit display when a Generic Name is selected
    function setupGenericNameAutoFill(selectId, categoryDisplayId, unitDisplayId) {
        const select = document.getElementById(selectId);
        const categoryDisplay = document.getElementById(categoryDisplayId);
        const unitDisplay = document.getElementById(unitDisplayId);
        if (!select) return;

        select.addEventListener('change', function() {
            const selected = select.options[select.selectedIndex];
            categoryDisplay.value = selected ? (selected.getAttribute('data-category') || '') : '';
            unitDisplay.value = selected ? (selected.getAttribute('data-unit') || '') : '';
        });
    }
    setupGenericNameAutoFill('generic_name_id', 'generic_name_category_display', 'generic_name_unit_display');
    setupGenericNameAutoFill('update_generic_name_id', 'update_generic_name_category_display', 'update_generic_name_unit_display');

    // Live-calculate each price tier as: retail x (1 - percent / 100), still hand-editable afterward
    function setupPriceTierCalc(retailId, tiers) {
        const retailInput = document.getElementById(retailId);
        if (!retailInput) return;

        function recalc(percentInput, valueInput) {
            const percent = parseFloat(percentInput.value);
            const retail = parseFloat(retailInput.value);
            if (!isNaN(percent) && !isNaN(retail)) {
                valueInput.value = (retail * (1 - percent / 100)).toFixed(2);
            }
        }

        tiers.forEach(([percentId, valueId]) => {
            const percentInput = document.getElementById(percentId);
            const valueInput = document.getElementById(valueId);
            if (!percentInput || !valueInput) return;
            percentInput.addEventListener('input', () => recalc(percentInput, valueInput));
            retailInput.addEventListener('input', () => recalc(percentInput, valueInput));
        });
    }
    setupPriceTierCalc('unit_price', [
        ['wholesale_percent', 'wholesale_price'],
        ['price_1_percent', 'price_1'],
        ['price_2_percent', 'price_2'],
        ['price_3_percent', 'price_3'],
    ]);
    setupPriceTierCalc('update_unit_price', [
        ['update_wholesale_percent', 'update_wholesale_price'],
        ['update_price_1_percent', 'update_price_1'],
        ['update_price_2_percent', 'update_price_2'],
        ['update_price_3_percent', 'update_price_3'],
    ]);

    // Handle view button click to populate the view modal
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const get = (attr) => this.getAttribute(attr);

            document.getElementById('view_item_id').textContent = get('data-id');
            document.getElementById('view_item_name').textContent = get('data-name');
            document.getElementById('view_generic_name').textContent = get('data-generic-name') || 'N/A';
            document.getElementById('view_brand_name').textContent = get('data-brand-name') || 'N/A';
            document.getElementById('view_serial_number').textContent = get('data-serial') || 'N/A';
            document.getElementById('view_tax').textContent = get('data-tax') || 'No Tax';
            document.getElementById('view_category').textContent = get('data-category');
            document.getElementById('view_unit').textContent = get('data-unit');
            document.getElementById('view_supplier').textContent = get('data-supplier');
            document.getElementById('view_description').textContent = get('data-description') || 'N/A';
            document.getElementById('view_quantity').textContent = get('data-quantity');
            document.getElementById('view_unit_cost').textContent = '₱' + parseFloat(get('data-unit-cost') || 0).toFixed(2);
            document.getElementById('view_threshold').textContent = get('data-threshold');
            document.getElementById('view_location').textContent = get('data-location') || 'N/A';
            document.getElementById('view_price').textContent = '₱' + parseFloat(get('data-price') || 0).toFixed(2);

            const formatTier = (percent, price) => {
                if (!price) return 'N/A';
                return '₱' + parseFloat(price).toFixed(2) + (percent ? ' (' + parseFloat(percent).toFixed(2) + '%)' : '');
            };
            document.getElementById('view_wholesale').textContent = formatTier(get('data-wholesale-percent'), get('data-wholesale-price'));
            document.getElementById('view_price_1').textContent = formatTier(get('data-price-1-percent'), get('data-price-1'));
            document.getElementById('view_price_2').textContent = formatTier(get('data-price-2-percent'), get('data-price-2'));
            document.getElementById('view_price_3').textContent = formatTier(get('data-price-3-percent'), get('data-price-3'));

            document.getElementById('view_batch_no').textContent = get('data-batch-no') || 'N/A';
            document.getElementById('view_expiration_date').textContent = get('data-expiration-date') || 'N/A';

            const viewImagePreview = document.getElementById('view_image_preview');
            const viewImageNone = document.getElementById('view_image_none');
            const image = get('data-image');
            if (image) {
                viewImagePreview.src = image;
                viewImagePreview.classList.remove('d-none');
                viewImageNone.classList.add('d-none');
            } else {
                viewImagePreview.classList.add('d-none');
                viewImageNone.classList.remove('d-none');
            }
        });
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const get = (attr) => this.getAttribute(attr);

            const genericNameSelect = document.getElementById('update_generic_name_id');
            genericNameSelect.value = get('data-generic-name-id') || '';
            genericNameSelect.dispatchEvent(new Event('change'));

            document.getElementById('update_brand_name').value = get('data-brand-name') || '';
            document.getElementById('update_serial_number').value = get('data-serial') || '';
            document.getElementById('update_tax_id').value = get('data-tax-id') || '';
            document.getElementById('update_supplier_id').value = get('data-supplier');
            document.getElementById('update_description').value = get('data-description');
            document.getElementById('update_quantity').value = get('data-quantity');
            document.getElementById('update_unit_cost').value = get('data-unit-cost') || '';
            document.getElementById('update_low_stock_threshold').value = get('data-threshold');
            document.getElementById('update_image').value = '';
            document.getElementById('update_batch_no').value = get('data-batch-no') || '';
            document.getElementById('update_expiration_date').value = get('data-expiration-date') || '';
            document.getElementById('update_location').value = get('data-location') || '';

            document.getElementById('update_unit_price').value = get('data-price') || '';
            document.getElementById('update_wholesale_percent').value = get('data-wholesale-percent') || '';
            document.getElementById('update_wholesale_price').value = get('data-wholesale-price') || '';
            document.getElementById('update_price_1_percent').value = get('data-price-1-percent') || '';
            document.getElementById('update_price_1').value = get('data-price-1') || '';
            document.getElementById('update_price_2_percent').value = get('data-price-2-percent') || '';
            document.getElementById('update_price_2').value = get('data-price-2') || '';
            document.getElementById('update_price_3_percent').value = get('data-price-3-percent') || '';
            document.getElementById('update_price_3').value = get('data-price-3') || '';

            const updateImagePreview = document.getElementById('update_image_preview');
            const updateImageNone = document.getElementById('update_image_none');
            const image = get('data-image');
            if (image) {
                updateImagePreview.src = image;
                updateImagePreview.classList.remove('d-none');
                updateImageNone.classList.add('d-none');
            } else {
                updateImagePreview.classList.add('d-none');
                updateImageNone.classList.remove('d-none');
            }

            // Set the form action to the update route
            const form = document.getElementById('updateItemForm');
            form.action = `{{ route('items.index') }}/${get('data-id')}`;
        });
    });

    // Clear the add item form when the modal is hidden
    const itemModal = document.getElementById('itemModal');
    itemModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('generic_name_id').value = '';
        document.getElementById('generic_name_category_display').value = '';
        document.getElementById('generic_name_unit_display').value = '';
        document.getElementById('brand_name').value = '';
        document.getElementById('serial_number').value = '';
        document.getElementById('tax_id').value = '';
        document.getElementById('image').value = '';
        document.getElementById('supplier_id').value = '';
        document.getElementById('description').value = '';
        document.getElementById('quantity').value = '';
        document.getElementById('unit_cost').value = '';
        document.getElementById('unit_price').value = '';
        document.getElementById('wholesale_percent').value = '';
        document.getElementById('wholesale_price').value = '';
        document.getElementById('price_1_percent').value = '';
        document.getElementById('price_1').value = '';
        document.getElementById('price_2_percent').value = '';
        document.getElementById('price_2').value = '';
        document.getElementById('price_3_percent').value = '';
        document.getElementById('price_3').value = '';
        document.getElementById('location').value = '';
        document.getElementById('low_stock_threshold').value = '';
        document.getElementById('batch_no').value = '';
        document.getElementById('expiration_date').value = '';
    });
</script>
@endsection
