@extends('layout.app')

@section('title', 'Inventory Adjustments')

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Adjustments</h5>
            <a href="{{ route('inventory-adjustments.create') }}" class="btn btn-primary">New Adjustment</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto" style="min-width: 300px;">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="Search..." value="{{ $search }}">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </div>
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\InventoryAdjustment::STATUSES as $value => $label)
                            <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Adjustment #</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Prepared By</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td><a href="{{ route('inventory-adjustments.show', $adjustment) }}">{{ $adjustment->adjustment_no }}</a></td>
                            <td>
                                <span class="badge bg-{{ $adjustment->isDraft() ? 'secondary' : 'success' }}">
                                    {{ \App\Models\InventoryAdjustment::STATUSES[$adjustment->status] ?? $adjustment->status }}
                                </span>
                            </td>
                            <td>{{ $adjustment->adjustment_date->format('Y-m-d') }}</td>
                            <td>{{ \App\Models\InventoryAdjustment::TYPES[$adjustment->adjustment_type] ?? $adjustment->adjustment_type }}</td>
                            <td>{{ $adjustment->description }}</td>
                            <td>{{ $adjustment->preparedBy->name ?? '—' }}</td>
                            <td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if($adjustment->isDraft())
                                            <a href="{{ route('inventory-adjustments.edit', $adjustment) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('inventory-adjustments.destroy', $adjustment) }}" method="POST" onsubmit="return confirm('Delete draft {{ $adjustment->adjustment_no }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bx bx-trash me-1"></i> Delete Draft
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('inventory-adjustments.edit', $adjustment) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            @if($adjustment->reversedBy)
                                                <span class="dropdown-item text-muted" title="Already written off by {{ $adjustment->reversedBy->adjustment_no }}">
                                                    <i class="bx bx-undo me-1"></i> Write-off
                                                </span>
                                            @else
                                                <form action="{{ route('inventory-adjustments.write-off', $adjustment) }}" method="POST" onsubmit="return confirm('Write off {{ $adjustment->adjustment_no }}? This reverses its quantities and takes you to a fresh form for the correct entry.');">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="bx bx-undo me-1"></i> Write-off
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No inventory adjustments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $adjustments->links() }}
        </div>
    </div>
@endsection
