@extends('layout.app')

@section('title', 'Activities Log')

@section('content')
<div class="card shadow-sm mt-3">
    <div class="card-header">
        <h5 class="mb-3">Activities Log</h5>
        <form action="{{ route('activity-logs.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">All modules</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">All sources</option>
                    @foreach($sources as $source)
                        <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucfirst($source) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                @if(request()->anyFilled(['user_id', 'module', 'source', 'date_from', 'date_to']))
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-sm btn-outline-danger">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        @if($activityLogs->count() > 0)
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Module</th>
                        <th>Source</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date/Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activityLogs as $log)
                        <tr>
                            <td>
                                @if($log->user)
                                    <strong>{{ $log->user->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $log->user->email }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->module)
                                    <span class="badge bg-label-secondary">{{ $log->module }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->source === 'pos')
                                    <span class="badge bg-info">POS</span>
                                @elseif($log->source === 'system')
                                    <span class="badge bg-dark">System</span>
                                @else
                                    <span class="badge bg-primary">Admin</span>
                                @endif
                            </td>
                            <td>
                                @if(in_array($log->action, ['login', 'created', 'reactivated']))
                                    <span class="badge bg-success">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                @elseif(in_array($log->action, ['logout', 'updated']))
                                    <span class="badge bg-warning">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                @elseif(in_array($log->action, ['deleted', 'login_failed', 'suspended', 'forced_logout']))
                                    <span class="badge bg-danger">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log->description)
                                    {{ Str::limit($log->description, 60) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->ip_address)
                                    <code class="text-dark">{{ $log->ip_address }}</code>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $log->created_at->format('M d, Y H:i:s') }}
                                    <br>
                                    <span class="badge bg-light text-dark">{{ $log->created_at->diffForHumans() }}</span>
                                </small>
                            </td>
                            <td>
                                @if($log->metadata || $log->loggable_type)
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#logDetailsModal"
                                        data-description="{{ $log->description }}"
                                        data-loggable="{{ $log->loggable_type ? class_basename($log->loggable_type) . ' #' . $log->loggable_id : '' }}"
                                        data-metadata="{{ $log->metadata ? json_encode($log->metadata, JSON_PRETTY_PRINT) : '' }}"
                                        onclick="showLogDetails(this)">
                                        Details
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-5 text-center">
                <i class="bx bx-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">No activity logs found.</p>
            </div>
        @endif
    </div>

    @if($activityLogs->hasPages())
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <small class="text-muted">
                        Showing {{ $activityLogs->firstItem() }}–{{ $activityLogs->lastItem() }}
                        of {{ $activityLogs->total() }} results
                    </small>
                </div>
                <div class="col d-flex justify-content-end">
                    <nav aria-label="Activity log pagination">
                        <ul class="pagination mb-0">

                            {{-- First Page --}}
                            <li class="page-item first {{ $activityLogs->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $activityLogs->onFirstPage() ? 'javascript:void(0);' : $activityLogs->url(1) }}">
                                    <i class="tf-icon bx bx-chevrons-left"></i>
                                </a>
                            </li>

                            {{-- Previous Page --}}
                            <li class="page-item prev {{ $activityLogs->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $activityLogs->onFirstPage() ? 'javascript:void(0);' : $activityLogs->previousPageUrl() }}">
                                    <i class="tf-icon bx bx-chevron-left"></i>
                                </a>
                            </li>

                            {{-- Page Numbers --}}
                            @foreach($activityLogs->getUrlRange(
                                max(1, $activityLogs->currentPage() - 2),
                                min($activityLogs->lastPage(), $activityLogs->currentPage() + 2)
                            ) as $page => $url)
                                <li class="page-item {{ $page == $activityLogs->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endforeach

                            {{-- Next Page --}}
                            <li class="page-item next {{ !$activityLogs->hasMorePages() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $activityLogs->hasMorePages() ? $activityLogs->nextPageUrl() : 'javascript:void(0);' }}">
                                    <i class="tf-icon bx bx-chevron-right"></i>
                                </a>
                            </li>

                            {{-- Last Page --}}
                            <li class="page-item last {{ !$activityLogs->hasMorePages() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $activityLogs->hasMorePages() ? $activityLogs->url($activityLogs->lastPage()) : 'javascript:void(0);' }}">
                                    <i class="tf-icon bx bx-chevrons-right"></i>
                                </a>
                            </li>

                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    @endif

</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="logDetailsDescription" class="mb-2"></p>
                <p id="logDetailsLoggable" class="text-muted small mb-3"></p>
                <pre id="logDetailsMetadata" class="bg-light p-2 rounded small" style="white-space: pre-wrap;"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function showLogDetails(button) {
        document.getElementById('logDetailsDescription').textContent = button.dataset.description || '';
        const loggable = button.dataset.loggable;
        document.getElementById('logDetailsLoggable').textContent = loggable ? `Affected record: ${loggable}` : '';
        document.getElementById('logDetailsMetadata').textContent = button.dataset.metadata || 'No additional metadata.';
    }
</script>
@endsection
