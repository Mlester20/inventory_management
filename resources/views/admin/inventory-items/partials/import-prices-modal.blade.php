<div class="modal fade" id="importPricesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('products.import-prices') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Prices</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        <strong>1.</strong> <a href="{{ route('products.export-prices') }}">Download all products</a>
                        (each with its Code), or <a href="{{ route('products.import-prices.template') }}">a blank template</a>.<br>
                        <strong>2.</strong> Fill in Cost, Retail, Wholesale % / P1 % / P2 % / P3 % and Tax, then upload it here.
                        Retail: either a <strong>Retail Markup %</strong> (price = Cost &times; (1 + %), needs a Cost)
                        or a typed <strong>Retail Price</strong> with the % left blank.
                        If the file has multiple sheets, only the one named "PRICES" is read.
                    </p>
                    <div class="mb-3">
                        <label for="prices_import_file" class="form-label">File</label>
                        <input type="file" name="file" id="prices_import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Only existing products are updated, found by <strong>Code</strong> (or, with the Code
                        blank, by Category + Generic Description + Brand). Nothing is ever created — a row that
                        matches no product is skipped and reported. A blank cell keeps the current value.
                        Wholesale and P1–P3 are a % <em>off</em> Retail; their peso amount is computed for you. Tax is VAT Inc
                        (VATable), VAT Ex (VAT-exempt) or Zero Vat. You can correct the file and import it
                        again at any time.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
