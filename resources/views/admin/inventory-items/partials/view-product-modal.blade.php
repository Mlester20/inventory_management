<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3" id="vp_image_wrapper" hidden>
                    <img id="vp_image" src="" alt="Product image" class="rounded border" style="max-height: 160px; max-width: 100%;">
                </div>

                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="text-muted small">Code</label>
                        <p class="fw-bold mb-0" id="vp_code"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Barcode</label>
                        <p class="fw-bold mb-0" id="vp_barcode"></p>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="text-muted small">Generic Description</label>
                    <p class="fw-bold mb-0" id="vp_generic"></p>
                </div>

                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="text-muted small">Brand Name</label>
                        <p class="fw-bold mb-0" id="vp_brand"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Cost</label>
                        <p class="fw-bold mb-0" id="vp_cost"></p>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="text-muted small">Item Description</label>
                    <p class="mb-0" id="vp_description"></p>
                </div>

                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="text-muted small">Supplier</label>
                        <p class="mb-0" id="vp_supplier"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Tax / VAT Classification</label>
                        <p class="mb-0" id="vp_tax"></p>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Price</th><th>%</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Retail</td><td id="vp_unit_price_percent"></td><td id="vp_unit_price"></td></tr>
                            <tr><td>Wholesale</td><td id="vp_wholesale_percent"></td><td id="vp_wholesale_price"></td></tr>
                            <tr><td>P. Level 1</td><td id="vp_price_1_percent"></td><td id="vp_price_1"></td></tr>
                            <tr><td>P. Level 2</td><td id="vp_price_2_percent"></td><td id="vp_price_2"></td></tr>
                            <tr><td>P. Level 3</td><td id="vp_price_3_percent"></td><td id="vp_price_3"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="row mb-2">
                    <div class="col-4">
                        <label class="text-muted small">Warehouse Qty</label>
                        <p class="fw-bold mb-0" id="vp_warehouse_qty"></p>
                    </div>
                    <div class="col-4">
                        <label class="text-muted small">POS Qty</label>
                        <p class="fw-bold mb-0" id="vp_pos_qty"></p>
                    </div>
                    <div class="col-4">
                        <label class="text-muted small">Total Qty</label>
                        <p class="fw-bold mb-0" id="vp_total_qty"></p>
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="text-muted small">FDA Reg. No.</label>
                        <p class="mb-0" id="vp_fda_no"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">FDA Reg. Exp.</label>
                        <p class="mb-0" id="vp_fda_exp"></p>
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-6 col-md-3">
                        <label class="text-muted small">Custom Field 1</label>
                        <p class="mb-0" id="vp_custom_1"></p>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="text-muted small">Custom Field 2</label>
                        <p class="mb-0" id="vp_custom_2"></p>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="text-muted small">Custom Field 3</label>
                        <p class="mb-0" id="vp_custom_3"></p>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="text-muted small">Custom Field 4</label>
                        <p class="mb-0" id="vp_custom_4"></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label class="text-muted small">Location</label>
                        <p class="mb-0" id="vp_location"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Low Stock Threshold</label>
                        <p class="mb-0" id="vp_threshold"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Every field here is read directly off the clicked trigger's data-*
    // attributes (already rendered server-side per row) — a pure display,
    // no fetch needed.
    document.querySelectorAll('.view-product-trigger').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const get = (attr) => this.getAttribute(attr) || '';
            const money = (v) => v === '' ? '—' : '₱' + parseFloat(v).toFixed(2);
            const percent = (v) => v === '' ? '—' : parseFloat(v).toFixed(2) + '%';

            document.getElementById('vp_code').textContent = get('data-code') || '—';
            document.getElementById('vp_barcode').textContent = get('data-barcode') || '—';
            document.getElementById('vp_generic').textContent = get('data-generic-description') || '—';
            document.getElementById('vp_brand').textContent = get('data-brand') || '—';
            document.getElementById('vp_cost').textContent = get('data-can-see-cost') === '1' ? money(get('data-cost')) : '••••';
            document.getElementById('vp_description').textContent = get('data-description') || '—';
            document.getElementById('vp_supplier').textContent = get('data-supplier') || '—';
            document.getElementById('vp_tax').textContent = get('data-tax') || '—';

            document.getElementById('vp_unit_price_percent').textContent = percent(get('data-unit-price-percent'));
            document.getElementById('vp_unit_price').textContent = money(get('data-unit-price'));
            document.getElementById('vp_wholesale_percent').textContent = percent(get('data-wholesale-percent'));
            document.getElementById('vp_wholesale_price').textContent = money(get('data-wholesale-price'));
            document.getElementById('vp_price_1_percent').textContent = percent(get('data-price-1-percent'));
            document.getElementById('vp_price_1').textContent = money(get('data-price-1'));
            document.getElementById('vp_price_2_percent').textContent = percent(get('data-price-2-percent'));
            document.getElementById('vp_price_2').textContent = money(get('data-price-2'));
            document.getElementById('vp_price_3_percent').textContent = percent(get('data-price-3-percent'));
            document.getElementById('vp_price_3').textContent = money(get('data-price-3'));

            document.getElementById('vp_warehouse_qty').textContent = get('data-warehouse-qty') || '0';
            document.getElementById('vp_pos_qty').textContent = get('data-pos-qty') || '0';
            document.getElementById('vp_total_qty').textContent = get('data-total-qty') || '0';

            document.getElementById('vp_fda_no').textContent = get('data-fda-reg-no') || '—';
            document.getElementById('vp_fda_exp').textContent = get('data-fda-reg-exp') || '—';
            document.getElementById('vp_custom_1').textContent = get('data-custom-1') || '—';
            document.getElementById('vp_custom_2').textContent = get('data-custom-2') || '—';
            document.getElementById('vp_custom_3').textContent = get('data-custom-3') || '—';
            document.getElementById('vp_custom_4').textContent = get('data-custom-4') || '—';
            document.getElementById('vp_location').textContent = get('data-location') || '—';
            document.getElementById('vp_threshold').textContent = get('data-threshold') || '—';

            const imageUrl = get('data-image-url');
            const imageWrapper = document.getElementById('vp_image_wrapper');
            if (imageUrl) {
                document.getElementById('vp_image').src = imageUrl;
                imageWrapper.hidden = false;
            } else {
                imageWrapper.hidden = true;
            }
        });
    });
})();
</script>
