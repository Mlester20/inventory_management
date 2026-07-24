@php
    $isUpdate = $mode === 'update';
    $prefix = $isUpdate ? 'update_' : '';
    $modalId = $isUpdate ? 'updateProductModal' : 'productModal';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form
            id="{{ $isUpdate ? 'updateProductForm' : '' }}"
            action="{{ $isUpdate ? '' : route('products.store') }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf
            @if($isUpdate) @method('PUT') @endif

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isUpdate ? 'Edit Item' : 'New Item' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" id="{{ $prefix }}code" class="form-control"
                            value="{{ $isUpdate ? '' : $nextProductCode }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Generic Description</label>
                        <select name="generic_name_id" id="{{ $prefix }}generic_name_id" class="form-select" required>
                            <option value="">-- Select Generic Description --</option>
                            @foreach ($genericNames as $genericName)
                                <option value="{{ $genericName->id }}"
                                    data-category="{{ $genericName->category->category_name }}"
                                    data-unit="{{ $genericName->unit }}">
                                    {{ $genericName->generic_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" id="{{ $prefix }}category_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" id="{{ $prefix }}unit_display" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand Name</label>
                        <input type="text" name="brand_name" id="{{ $prefix }}brand_name" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Description</label>
                        <textarea name="description" id="{{ $prefix }}description" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="{{ $prefix }}barcode" class="form-control">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" id="{{ $prefix }}supplier_id" class="form-select">
                                <option value="">-- Select Supplier --</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax / VAT Classification</label>
                            <select name="tax_id" id="{{ $prefix }}tax_id" class="form-select">
                                <option value="">-- No Tax --</option>
                                @foreach ($taxes as $tax)
                                    <option value="{{ $tax->id }}">{{ $tax->name }} ({{ $tax->rate }}%)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cost</label>
                        <input type="number" step="0.01" name="unit_cost" id="{{ $prefix }}unit_cost" class="form-control">
                    </div>

                    <table class="table table-sm">
                        <thead>
                            <tr><th>Price</th><th>%</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['label' => 'Retail', 'percent' => 'unit_price_percent', 'value' => 'unit_price', 'required' => true],
                                ['label' => 'Wholesale', 'percent' => 'wholesale_percent', 'value' => 'wholesale_price'],
                                ['label' => 'P. Level 1', 'percent' => 'price_1_percent', 'value' => 'price_1'],
                                ['label' => 'P. Level 2', 'percent' => 'price_2_percent', 'value' => 'price_2'],
                                ['label' => 'P. Level 3', 'percent' => 'price_3_percent', 'value' => 'price_3'],
                            ] as $tier)
                                <tr>
                                    <td class="align-middle">{{ $tier['label'] }}</td>
                                    <td>
                                        <input type="number" step="0.01" name="{{ $tier['percent'] }}" id="{{ $prefix }}{{ $tier['percent'] }}" class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="{{ $tier['value'] }}" id="{{ $prefix }}{{ $tier['value'] }}" class="form-control form-control-sm" {{ !empty($tier['required']) ? 'required' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">FDA Reg. No.</label>
                            <input type="text" name="fda_reg_no" id="{{ $prefix }}fda_reg_no" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">FDA Reg. Exp.</label>
                            <input type="date" name="fda_reg_exp" id="{{ $prefix }}fda_reg_exp" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 1</label>
                            <input type="text" name="custom_field_1" id="{{ $prefix }}custom_field_1" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 2</label>
                            <input type="text" name="custom_field_2" id="{{ $prefix }}custom_field_2" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 3</label>
                            <input type="text" name="custom_field_3" id="{{ $prefix }}custom_field_3" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 4</label>
                            <input type="text" name="custom_field_4" id="{{ $prefix }}custom_field_4" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="{{ $prefix }}location" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" id="{{ $prefix }}low_stock_threshold" class="form-control" value="{{ $isUpdate ? '' : 5 }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" id="{{ $prefix }}image" class="form-control" accept="image/png,image/jpeg,image/webp">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">{{ $isUpdate ? 'Update' : 'Create' }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const prefix = '{{ $prefix }}';
    const genericSelect = document.getElementById(prefix + 'generic_name_id');
    const categoryDisplay = document.getElementById(prefix + 'category_display');
    const unitDisplay = document.getElementById(prefix + 'unit_display');

    if (genericSelect) {
        genericSelect.addEventListener('change', function () {
            const selected = genericSelect.options[genericSelect.selectedIndex];
            categoryDisplay.value = selected ? (selected.getAttribute('data-category') || '') : '';
            unitDisplay.value = selected ? (selected.getAttribute('data-unit') || '') : '';
        });
    }

    const retailInput = document.getElementById(prefix + 'unit_price');
    const tiers = [
        [prefix + 'wholesale_percent', prefix + 'wholesale_price'],
        [prefix + 'price_1_percent', prefix + 'price_1'],
        [prefix + 'price_2_percent', prefix + 'price_2'],
        [prefix + 'price_3_percent', prefix + 'price_3'],
    ];

    function recalc(percentInput, valueInput) {
        const percent = parseFloat(percentInput.value);
        const retail = parseFloat(retailInput.value);
        if (!isNaN(percent) && !isNaN(retail)) {
            valueInput.value = (retail * (1 - percent / 100)).toFixed(2);
        }
    }

    if (retailInput) {
        tiers.forEach(([percentId, valueId]) => {
            const percentInput = document.getElementById(percentId);
            const valueInput = document.getElementById(valueId);
            if (!percentInput || !valueInput) return;
            percentInput.addEventListener('input', () => recalc(percentInput, valueInput));
            retailInput.addEventListener('input', () => recalc(percentInput, valueInput));
        });
    }

    @if($isUpdate)
    document.querySelectorAll('.edit-product-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const get = (attr) => this.getAttribute(attr);

            document.getElementById('update_code').value = get('data-code') || '';
            const select = document.getElementById('update_generic_name_id');
            select.value = get('data-generic-name-id') || '';
            select.dispatchEvent(new Event('change'));

            document.getElementById('update_brand_name').value = get('data-brand-name') || '';
            document.getElementById('update_description').value = get('data-description') || '';
            document.getElementById('update_barcode').value = get('data-barcode') || '';
            document.getElementById('update_supplier_id').value = get('data-supplier-id') || '';
            document.getElementById('update_tax_id').value = get('data-tax-id') || '';
            document.getElementById('update_unit_cost').value = get('data-unit-cost') || '';
            document.getElementById('update_unit_price_percent').value = get('data-unit-price-percent') || '';
            document.getElementById('update_unit_price').value = get('data-unit-price') || '';
            document.getElementById('update_wholesale_percent').value = get('data-wholesale-percent') || '';
            document.getElementById('update_wholesale_price').value = get('data-wholesale-price') || '';
            document.getElementById('update_price_1_percent').value = get('data-price-1-percent') || '';
            document.getElementById('update_price_1').value = get('data-price-1') || '';
            document.getElementById('update_price_2_percent').value = get('data-price-2-percent') || '';
            document.getElementById('update_price_2').value = get('data-price-2') || '';
            document.getElementById('update_price_3_percent').value = get('data-price-3-percent') || '';
            document.getElementById('update_price_3').value = get('data-price-3') || '';
            document.getElementById('update_fda_reg_no').value = get('data-fda-reg-no') || '';
            document.getElementById('update_fda_reg_exp').value = get('data-fda-reg-exp') || '';
            document.getElementById('update_custom_field_1').value = get('data-custom-1') || '';
            document.getElementById('update_custom_field_2').value = get('data-custom-2') || '';
            document.getElementById('update_custom_field_3').value = get('data-custom-3') || '';
            document.getElementById('update_custom_field_4').value = get('data-custom-4') || '';
            document.getElementById('update_location').value = get('data-location') || '';
            document.getElementById('update_low_stock_threshold').value = get('data-threshold') || '';
            document.getElementById('update_image').value = '';

            document.getElementById('updateProductForm').action = `{{ url('admin/products') }}/${get('data-id')}`;
        });
    });
    @endif
})();
</script>
