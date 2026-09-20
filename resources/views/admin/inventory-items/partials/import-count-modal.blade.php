<div class="modal fade" id="importCountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('inventory-items.import-count') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Stock Count</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        <strong>1.</strong> <a href="{{ route('inventory-items.export-count') }}">Download stock for counting</a>
                        (every lot in the Warehouse with its system quantity), or
                        <a href="{{ route('inventory-items.import-count.template') }}">a blank template</a>.<br>
                        <strong>2.</strong> Fill in <strong>Counted Qty</strong> — the actual number you counted — and upload it here.
                        If the file has multiple sheets, only the one named "COUNT" is read.
                    </p>
                    <div class="mb-3">
                        <label for="count_import_file" class="form-label">File</label>
                        <input type="file" name="file" id="count_import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Each lot's Warehouse quantity is set to the Counted Qty. The difference is posted as
                        Inventory Adjustments ("Correction - Increase" / "Correction - Decrease"), so it appears
                        in Product History and can be written off. Only <strong>existing</strong> lots are
                        corrected — an unknown product or lot is skipped and reported (new lots come from Import
                        Opening Inventory). A blank Counted Qty leaves the lot alone; 0 means none on hand.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply count</button>
                </div>
            </div>
        </form>
    </div>
</div>
