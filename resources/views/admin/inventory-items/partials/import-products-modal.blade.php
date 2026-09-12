<div class="modal fade" id="importProductsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload an Excel (.xlsx/.xls) or CSV file with columns: Category, Unit, Generic
                        Description, Brand (optional), Cost (optional), Unit Price (optional). If the file
                        has multiple sheets, only the one named "PRODUCTS" is read — every other sheet is
                        ignored, so the full source workbook can be uploaded as-is.
                        <a href="{{ route('products.import.template') }}">Download the template</a>.
                    </p>
                    <div class="mb-3">
                        <label for="product_import_file" class="form-label">File</label>
                        <input type="file" name="file" id="product_import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        A Category or Generic Description that doesn't exist yet is created automatically.
                        Rows repeating the same Category + Generic Description + Brand are skipped as
                        duplicates, and a Generic Description that already exists under a different
                        Category is skipped and reported rather than guessed. Products with no Unit Price
                        in the file import at ₱0.00, pending the real price.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </div>
        </form>
    </div>
</div>
