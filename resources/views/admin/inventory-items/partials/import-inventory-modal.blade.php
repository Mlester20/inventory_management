<div class="modal fade" id="importInventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('inventory-items.import-stock') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Opening Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload an Excel (.xlsx/.xls) or CSV file with columns: Category, Generic
                        Description, Brand, Lot No, Expiry Date, Qty. One row per lot. If the file has
                        multiple sheets, only the one named "INVENTORY" is read.
                        <a href="{{ route('inventory-items.import-stock.template') }}">Download the template</a>.
                    </p>
                    <div class="mb-3">
                        <label for="inventory_import_file" class="form-label">File</label>
                        <input type="file" name="file" id="inventory_import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Products must already exist — a row whose Category, Generic Description and Brand
                        match no product is skipped and reported, never created. Lot No and Expiry Date may
                        be left blank. Stock is added to the Warehouse as one "Opening Balance" Inventory
                        Adjustment. A lot the product already has (same Lot No) is skipped, so importing the
                        same file twice adds nothing. Rows with no Qty are ignored. Every lot goes through
                        the stock ledger one by one (about 15 seconds per 1,000 lots), so for very large
                        files it is safer to upload in parts of around 2,000 rows.
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
