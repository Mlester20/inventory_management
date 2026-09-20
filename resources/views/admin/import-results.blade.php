@extends('layout.app')

@section('title', 'Import Results')

@section('content')
@php
    $typeLabels = ['products' => 'Products', 'customers' => 'Customers', 'suppliers' => 'Suppliers', 'inventory' => 'Opening Inventory', 'prices' => 'Update Prices'];
    $reasonBadges = [
        'product_not_found' => 'danger',
        'duplicate_in_file' => 'warning',
        'already_in_system' => 'info',
        'different_category' => 'danger',
        'invalid_data' => 'secondary',
    ];
@endphp

<div class="card shadow-sm mt-3">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1">Import Results</h5>
                <small class="text-muted">Rows that were skipped during Excel imports, with the reason. Correct them in your Excel file and import again.</small>
            </div>
            @if(request()->filled('batch') && $rows->total() > 0)
                <a href="{{ route('import-results.export', array_filter(['batch' => request('batch'), 'reason' => request('reason')])) }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bx bx-download me-1"></i>Download as Excel
                </a>
            @endif
        </div>

        @if($batches->isNotEmpty())
            <label class="form-label small text-muted mb-1">Recent imports</label>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date/Time</th>
                            <th>Import</th>
                            <th>Imported by</th>
                            <th class="text-end">Rows skipped</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $batch)
                            <tr class="{{ request('batch') === $batch->batch_id ? 'table-active' : '' }}">
                                <td>{{ \Illuminate\Support\Carbon::parse($batch->imported_at)->format('M d, Y H:i') }}</td>
                                <td>{{ $typeLabels[$batch->import_type] ?? ucfirst($batch->import_type) }}</td>
                                <td>{{ $users[$batch->imported_by] ?? '—' }}</td>
                                <td class="text-end">{{ number_format($batch->skipped_count) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('import-results.index', ['batch' => $batch->batch_id]) }}" class="btn btn-xs btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form action="{{ route('import-results.index') }}" method="GET" class="row g-2 align-items-end">
            @if(request()->filled('batch'))
                <input type="hidden" name="batch" value="{{ request('batch') }}">
            @endif
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Import type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Reason</label>
                <select name="reason" class="form-select form-select-sm">
                    <option value="">All reasons</option>
                    @foreach($reasons as $value => $label)
                        <option value="{{ $value }}" @selected(request('reason') === $value)>
                            {{ $label }}@if(isset($reasonCounts[$value])) ({{ $reasonCounts[$value] }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                @if(request()->anyFilled(['batch', 'type', 'reason']))
                    <a href="{{ route('import-results.index') }}" class="btn btn-sm btn-outline-danger">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        @if($rows->count() > 0)
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Excel Row</th>
                        <th>Import</th>
                        <th>Row data</th>
                        <th>Reason</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->sheet_row ?? '—' }}</td>
                            <td><span class="badge bg-label-secondary">{{ $typeLabels[$row->import_type] ?? $row->import_type }}</span></td>
                            <td style="min-width: 260px;">
                                @foreach(($row->row_data ?? []) as $label => $value)
                                    @if($value !== null && $value !== '')
                                        <div><small class="text-muted">{{ $label }}:</small> {{ $value }}</div>
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                <span class="badge bg-label-{{ $reasonBadges[$row->reason] ?? 'secondary' }}">
                                    {{ $reasons[$row->reason] ?? $row->reason }}
                                </span>
                            </td>
                            <td style="max-width: 320px;"><small>{{ $row->details }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-5 text-center">
                <i class="bx bx-check-circle text-success" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">No skipped rows to show.</p>
            </div>
        @endif
    </div>

    @if($rows->hasPages())
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <small class="text-muted">
                        Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ number_format($rows->total()) }} rows
                    </small>
                </div>
                <div class="col d-flex justify-content-end">
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
