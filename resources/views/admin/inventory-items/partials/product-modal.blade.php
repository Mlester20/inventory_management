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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="{{ $prefix }}code" class="form-control"
                                placeholder="e.g., 00012" value="{{ $isUpdate ? '' : $nextProductCode }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="{{ $prefix }}barcode" class="form-control" placeholder="e.g., 4800001234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Generic Description</label>
                        @once
                            <style>
                                .generic-suggestions {
                                    background-color: #fff;
                                    border: 1px solid rgba(0, 0, 0, .15);
                                    border-radius: .375rem;
                                    box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
                                }
                                .generic-suggestions button {
                                    display: block;
                                    width: 100%;
                                    text-align: left;
                                    background-color: #fff;
                                    border: 0;
                                    border-bottom: 1px solid #f1f1f1;
                                    padding: .375rem .75rem;
                                    cursor: pointer;
                                    white-space: normal;
                                    word-break: break-word;
                                }
                                .generic-suggestions button:last-child { border-bottom: 0; }
                                .generic-suggestions button:hover { background-color: #f8f9fa; }
                            </style>
                        @endonce
                        {{-- A custom dropdown rather than a native <datalist>: the browser's own popup clips long
                             generic descriptions to its internal width, so the whole name never showed. --}}
                        <div class="position-relative">
                            <input type="text" class="form-control generic-name-search" id="{{ $prefix }}generic_name_search"
                                placeholder="Search generic name..." autocomplete="off" required>
                            <div class="generic-suggestions position-absolute w-100" id="{{ $prefix }}generic_suggestions"
                                style="z-index: 1060; max-height: 280px; overflow-y: auto; display: none;"></div>
                        </div>
                        <input type="hidden" name="generic_name_id" id="{{ $prefix }}generic_name_id">
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand Name</label>
                            <input type="text" name="brand_name" id="{{ $prefix }}brand_name" class="form-control" placeholder="e.g., Biogesic">
                        </div>
                        @if(Auth::user()->role === 'admin')
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost</label>
                                <input type="number" step="0.01" name="unit_cost" id="{{ $prefix }}unit_cost" class="form-control" placeholder="e.g., 2.50">
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Description</label>
                        <textarea name="description" id="{{ $prefix }}description" class="form-control" rows="2" placeholder="e.g., Pain reliever and fever reducer" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="{{ $prefix }}supplier_search"
                                placeholder="Search supplier..." autocomplete="off">
                            <div class="generic-suggestions position-absolute w-100" id="{{ $prefix }}supplier_suggestions"
                                style="z-index: 1060; max-height: 280px; overflow-y: auto; display: none;"></div>
                        </div>
                        <input type="hidden" name="supplier_id" id="{{ $prefix }}supplier_id">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax / VAT Classification</label>
                            <select name="tax_id" id="{{ $prefix }}tax_id" class="form-select">
                                <option value="">-- No Tax --</option>
                                @foreach ($taxes as $tax)
                                    <option value="{{ $tax->id }}" @selected(! $isUpdate && strtolower($tax->name) === 'vat')>{{ $tax->name }} ({{ $tax->rate }}%)</option>
                                @endforeach
                            </select>
                        </div>
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
                                        <input type="number" step="0.01" name="{{ $tier['percent'] }}" id="{{ $prefix }}{{ $tier['percent'] }}" class="form-control form-control-sm" placeholder="e.g., 5">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="{{ $tier['value'] }}" id="{{ $prefix }}{{ $tier['value'] }}" class="form-control form-control-sm" placeholder="e.g., 4.75" {{ !empty($tier['required']) ? 'required' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">FDA Reg. No.</label>
                            <input type="text" name="fda_reg_no" id="{{ $prefix }}fda_reg_no" class="form-control" placeholder="e.g., FR-12345">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">FDA Reg. Exp.</label>
                            <input type="date" name="fda_reg_exp" id="{{ $prefix }}fda_reg_exp" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 1</label>
                            <input type="text" name="custom_field_1" id="{{ $prefix }}custom_field_1" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 2</label>
                            <input type="text" name="custom_field_2" id="{{ $prefix }}custom_field_2" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 3</label>
                            <input type="text" name="custom_field_3" id="{{ $prefix }}custom_field_3" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Field 4</label>
                            <input type="text" name="custom_field_4" id="{{ $prefix }}custom_field_4" class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="{{ $prefix }}location" class="form-control" placeholder="e.g., Shelf A-3, Warehouse 2">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" id="{{ $prefix }}low_stock_threshold" class="form-control" placeholder="e.g., 10" value="{{ $isUpdate ? '' : 5 }}" required>
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

@once
<script>
    // Shared by both the create and update instances of this modal (each
    // include renders its own <datalist>, but the lookup data only needs
    // to exist once per page).
    const PRODUCT_GENERIC_NAMES = @json($genericNamesForJs);

    function productGenericLabel(g) {
        return `${g.generic_name} (${g.unit}) — ${g.category_name}`;
    }

    function findProductGenericByLabel(label) {
        return PRODUCT_GENERIC_NAMES.find(g => productGenericLabel(g) === label);
    }

    function findProductGenericById(id) {
        return PRODUCT_GENERIC_NAMES.find(g => String(g.id) === String(id));
    }

    const PRODUCT_SUPPLIERS = @json($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->supplier_name])->values());

    function findProductSupplierByName(name) {
        return PRODUCT_SUPPLIERS.find(s => s.name === name) || null;
    }

    function findProductSupplierById(id) {
        return PRODUCT_SUPPLIERS.find(s => String(s.id) === String(id)) || null;
    }
</script>
@endonce

<script>
(function () {
    const prefix = '{{ $prefix }}';
    const genericSearchInput = document.getElementById(prefix + 'generic_name_search');
    const genericIdInput = document.getElementById(prefix + 'generic_name_id');
    const categoryDisplay = document.getElementById(prefix + 'category_display');
    const unitDisplay = document.getElementById(prefix + 'unit_display');

    function applyGeneric(generic) {
        genericIdInput.value = generic ? generic.id : '';
        categoryDisplay.value = generic ? generic.category_name : '';
        unitDisplay.value = generic ? generic.unit : '';
    }

    const genericSuggestions = document.getElementById(prefix + 'generic_suggestions');

    // Up to 100 matching generics as wrapped rows; a field already holding an exact label
    // browses the whole list instead of filtering down to that single entry.
    function showGenericSuggestions() {
        const typed = genericSearchInput.value.trim().toLowerCase();
        const browsing = !typed || !!findProductGenericByLabel(genericSearchInput.value);
        const matches = PRODUCT_GENERIC_NAMES
            .filter(g => browsing || productGenericLabel(g).toLowerCase().includes(typed))
            .slice(0, 100);

        genericSuggestions.replaceChildren(...matches.map(g => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'small';
            button.dataset.id = g.id;
            button.textContent = productGenericLabel(g);
            return button;
        }));
        genericSuggestions.style.display = matches.length ? 'block' : 'none';
    }

    if (genericSearchInput) {
        genericSearchInput.addEventListener('input', function () {
            applyGeneric(findProductGenericByLabel(this.value));
            showGenericSuggestions();
        });
        genericSearchInput.addEventListener('focus', showGenericSuggestions);
        genericSearchInput.addEventListener('blur', () => {
            setTimeout(() => { genericSuggestions.style.display = 'none'; }, 150);
        });
        genericSuggestions.addEventListener('mousedown', function (e) {
            const button = e.target.closest('[data-id]');
            if (!button) return;
            e.preventDefault();
            const generic = findProductGenericById(button.dataset.id);
            if (!generic) return;
            genericSearchInput.value = productGenericLabel(generic);
            applyGeneric(generic);
            genericSuggestions.style.display = 'none';
        });
    }

    const supplierSearchInput = document.getElementById(prefix + 'supplier_search');
    const supplierIdInput = document.getElementById(prefix + 'supplier_id');
    const supplierSuggestions = document.getElementById(prefix + 'supplier_suggestions');

    // Same custom dropdown as the Generic Description above — the browser's own datalist popup
    // clipped long supplier names. A field already holding an exact name browses the full list.
    function showSupplierSuggestions() {
        const typed = supplierSearchInput.value.trim().toLowerCase();
        const browsing = !typed || !!findProductSupplierByName(supplierSearchInput.value);
        const matches = PRODUCT_SUPPLIERS
            .filter(s => browsing || s.name.toLowerCase().includes(typed))
            .slice(0, 100);

        supplierSuggestions.replaceChildren(...matches.map(s => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'small';
            button.dataset.id = s.id;
            button.textContent = s.name;
            return button;
        }));
        supplierSuggestions.style.display = matches.length ? 'block' : 'none';
    }

    if (supplierSearchInput) {
        supplierSearchInput.addEventListener('input', function () {
            const match = findProductSupplierByName(this.value);
            supplierIdInput.value = match ? match.id : '';
            showSupplierSuggestions();
        });
        supplierSearchInput.addEventListener('focus', showSupplierSuggestions);
        supplierSearchInput.addEventListener('blur', () => {
            setTimeout(() => { supplierSuggestions.style.display = 'none'; }, 150);
        });
        supplierSuggestions.addEventListener('mousedown', function (e) {
            const button = e.target.closest('[data-id]');
            if (!button) return;
            e.preventDefault();
            const supplier = findProductSupplierById(button.dataset.id);
            if (!supplier) return;
            supplierSearchInput.value = supplier.name;
            supplierIdInput.value = supplier.id;
            supplierSuggestions.style.display = 'none';
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

    // Retail is a mark-up on Cost. Fill the % and the price computes
    // (Cost x (1 + %/100)); or type the Retail Price yourself, which clears the %.
    const costInput = document.getElementById(prefix + 'unit_cost');
    const retailPercentInput = document.getElementById(prefix + 'unit_price_percent');

    function recalcRetail() {
        const cost = parseFloat(costInput.value);
        const percent = parseFloat(retailPercentInput.value);
        if (isNaN(cost) || isNaN(percent)) return;
        retailInput.value = (cost * (1 + percent / 100)).toFixed(2);
        retailInput.dispatchEvent(new Event('input'));
    }

    if (retailInput && costInput && retailPercentInput) {
        costInput.addEventListener('input', recalcRetail);
        retailPercentInput.addEventListener('input', recalcRetail);
        retailInput.addEventListener('input', function (event) {
            if (event.isTrusted) retailPercentInput.value = '';
        });
    }

    @if($isUpdate)
    document.querySelectorAll('.edit-product-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const get = (attr) => this.getAttribute(attr);

            document.getElementById('update_code').value = get('data-code') || '';
            const generic = findProductGenericById(get('data-generic-name-id'));
            genericSearchInput.value = generic ? productGenericLabel(generic) : '';
            applyGeneric(generic);

            document.getElementById('update_brand_name').value = get('data-brand-name') || '';
            document.getElementById('update_description').value = get('data-description') || '';
            document.getElementById('update_barcode').value = get('data-barcode') || '';
            const updateSupplier = findProductSupplierById(get('data-supplier-id'));
            document.getElementById('update_supplier_search').value = updateSupplier ? updateSupplier.name : '';
            document.getElementById('update_supplier_id').value = updateSupplier ? updateSupplier.id : '';
            document.getElementById('update_tax_id').value = get('data-tax-id') || '';
            if (document.getElementById('update_unit_cost')) {
                document.getElementById('update_unit_cost').value = get('data-unit-cost') || '';
            }
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
    @else
    // Clone reuses the exact same data-* attributes the Edit button
    // already carries per row — everything copies over except Code
    // (keeps its own auto-suggested next value) and Barcode (blank),
    // since both must stay unique per product. Brand and the price
    // tiers copy over too, editable like every other field — those are
    // simply the ones expected to actually change between brand variants
    // of the same generic item.
    document.querySelectorAll('.clone-product-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const get = (attr) => this.getAttribute(attr);

            document.getElementById('barcode').value = '';
            const generic = findProductGenericById(get('data-generic-name-id'));
            genericSearchInput.value = generic ? productGenericLabel(generic) : '';
            applyGeneric(generic);

            document.getElementById('brand_name').value = get('data-brand-name') || '';
            document.getElementById('description').value = get('data-description') || '';
            const cloneSupplier = findProductSupplierById(get('data-supplier-id'));
            document.getElementById('supplier_search').value = cloneSupplier ? cloneSupplier.name : '';
            document.getElementById('supplier_id').value = cloneSupplier ? cloneSupplier.id : '';
            document.getElementById('tax_id').value = get('data-tax-id') || '';
            if (document.getElementById('unit_cost')) {
                document.getElementById('unit_cost').value = get('data-unit-cost') || '';
            }
            document.getElementById('unit_price_percent').value = get('data-unit-price-percent') || '';
            document.getElementById('unit_price').value = get('data-unit-price') || '';
            document.getElementById('wholesale_percent').value = get('data-wholesale-percent') || '';
            document.getElementById('wholesale_price').value = get('data-wholesale-price') || '';
            document.getElementById('price_1_percent').value = get('data-price-1-percent') || '';
            document.getElementById('price_1').value = get('data-price-1') || '';
            document.getElementById('price_2_percent').value = get('data-price-2-percent') || '';
            document.getElementById('price_2').value = get('data-price-2') || '';
            document.getElementById('price_3_percent').value = get('data-price-3-percent') || '';
            document.getElementById('price_3').value = get('data-price-3') || '';
            document.getElementById('fda_reg_no').value = get('data-fda-reg-no') || '';
            document.getElementById('fda_reg_exp').value = get('data-fda-reg-exp') || '';
            document.getElementById('custom_field_1').value = get('data-custom-1') || '';
            document.getElementById('custom_field_2').value = get('data-custom-2') || '';
            document.getElementById('custom_field_3').value = get('data-custom-3') || '';
            document.getElementById('custom_field_4').value = get('data-custom-4') || '';
            document.getElementById('location').value = get('data-location') || '';
            document.getElementById('low_stock_threshold').value = get('data-threshold') || '';
            document.getElementById('image').value = '';
        });
    });

    // A clone leaves the form pre-filled — a genuine "New Item" open right
    // after must not inherit that leftover data.
    const newProductBtn = document.getElementById('newProductBtn');
    if (newProductBtn) {
        newProductBtn.addEventListener('click', function () {
            document.getElementById('productModal').querySelector('form').reset();
        });
    }
    @endif
})();
</script>
