@extends('layout.app')

@section('title', 'Restock Items')

@section('content')
    <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Restock Items</h3>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="showTab('items-tab')">
                    <i class="fas fa-boxes"></i> Items
                </button>
                <button type="button" class="btn btn-outline-info" onclick="showTab('history-tab')">
                    <i class="fas fa-history"></i> History
                </button>
            </div>
        </div>

        @if ($message = Session::get('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($message = Session::get('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Items Tab -->
        <div id="items-tab" class="tab-content">
            <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Current Stock</th>
                        <th>Low Stock Threshold</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->item_name }}</strong>
                            </td>
                            <td>{{ $item->category->category_name ?? 'N/A' }}</td>
                            <td>{{ $item->supplier->supplier_name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge bg-info">{{ $item->quantity }}</span>
                            </td>
                            <td>{{ $item->low_stock_threshold }}</td>
                            <td>
                                @if ($item->quantity <= 0)
                                    <span class="badge bg-danger">Out of Stock</span>
                                @elseif ($item->isLowOnStock())
                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                @else
                                    <span class="badge bg-success">In Stock</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#restockModal{{ $item->id }}">
                                    <i class="fas fa-plus"></i> Restock
                                </button>
                            </td>
                        </tr>

                        <!-- Restock Modal -->
                        <div class="modal fade" id="restockModal{{ $item->id }}" tabindex="-1"
                            aria-labelledby="restockLabel{{ $item->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="restockLabel{{ $item->id }}">
                                            Restock: {{ $item->item_name }}
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="{{ route('stock.restock', $item) }}" method="POST">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="alert alert-info mb-3">
                                                <strong>Current Stock:</strong> {{ $item->quantity }} units
                                            </div>

                                            <div class="mb-3">
                                                <label for="quantity{{ $item->id }}" class="form-label">
                                                    Quantity to Add
                                                </label>
                                                <input type="number" name="quantity"
                                                    id="quantity{{ $item->id }}"
                                                    class="form-control @error('quantity') is-invalid @enderror"
                                                    placeholder="Enter quantity"
                                                    min="1"
                                                    required
                                                    value="{{ old('quantity') }}"
                                                >
                                                @error('quantity')
                                                    <div class="invalid-feedback d-block">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="remarks{{ $item->id }}" class="form-label">
                                                    Remarks (Optional)
                                                </label>
                                                <textarea name="remarks" id="remarks{{ $item->id }}"
                                                    class="form-control @error('remarks') is-invalid @enderror"
                                                    placeholder="e.g., Purchase Order #123"
                                                    rows="2">{{ old('remarks') }}</textarea>
                                                @error('remarks')
                                                    <div class="invalid-feedback d-block">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary"
                                                data-bs-dismiss="modal">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Restock
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-inbox"></i> No items found. Create items first.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content" style="display: none;">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-3">Stock Movement History</h5>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-2">Filter by Type:</label>
                            <select class="form-select form-select-sm" id="historyTypeFilter" onchange="filterHistory()">
                                <option value="">All Movements</option>
                                <option value="in">In (Restock)</option>
                                <option value="out">Out (Deduction)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-2">Filter by Item:</label>
                            <select class="form-select form-select-sm" id="historyItemFilter" onchange="filterHistory()">
                                <option value="">All Items</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-2">&nbsp;</label>
                            <button class="btn btn-sm btn-outline-secondary w-100" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="historyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Remarks</th>
                                    <th>Updated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $allMovements = collect();
                                    foreach ($items as $item) {
                                        $movements = $item->stockMovements()
                                            ->with('user')
                                            ->get();
                                        $allMovements = $allMovements->merge($movements);
                                    }
                                    $allMovements = $allMovements->sortByDesc('created_at');
                                @endphp

                                @forelse ($allMovements as $movement)
                                    <tr class="history-row" data-type="{{ $movement->type }}" data-item="{{ $movement->item_id }}">
                                        <td>
                                            <small>{{ $movement->created_at->format('M d, Y H:i') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $movement->item->item_name ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            @if ($movement->type === 'in')
                                                <span class="badge bg-success">In (Restock)</span>
                                            @else
                                                <span class="badge bg-danger">Out (Deduction)</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $movement->quantity }}</strong>
                                        </td>
                                        <td>
                                            <small>{{ $movement->remarks ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <small>{{ $movement->user->name ?? 'System' }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox"></i> No stock movements recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tabs
            document.getElementById('items-tab').style.display = 'none';
            document.getElementById('history-tab').style.display = 'none';

            // Show selected tab
            document.getElementById(tabName).style.display = 'block';

            // Update button active state
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Refresh filter when history tab is shown
            if (tabName === 'history-tab') {
                filterHistory();
            }
        }

        // Filter history table
        function filterHistory() {
            const typeFilter = document.getElementById('historyTypeFilter').value;
            const itemFilter = document.getElementById('historyItemFilter').value;
            const rows = document.querySelectorAll('.history-row');

            rows.forEach(row => {
                const rowType = row.getAttribute('data-type');
                const rowItem = row.getAttribute('data-item');

                let show = true;

                if (typeFilter && rowType !== typeFilter) {
                    show = false;
                }

                if (itemFilter && rowItem !== itemFilter) {
                    show = false;
                }

                row.style.display = show ? '' : 'none';
            });
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('historyTypeFilter').value = '';
            document.getElementById('historyItemFilter').value = '';
            filterHistory();
        }

        // Automatically show validation errors in the correct modal
        @if ($errors->any())
            @foreach ($items as $item)
                @if ($errors->has('quantity') || $errors->has('remarks'))
                    var restockModal = new bootstrap.Modal(document.getElementById('restockModal{{ $item->id }}'));
                    restockModal.show();
                    break;
                @endif
            @endforeach
        @endif
    </script>
@endsection