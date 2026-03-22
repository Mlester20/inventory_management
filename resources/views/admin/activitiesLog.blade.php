@extends('layout.app')

@section('title', 'Activities Log')

@section('content')
<div class="card shadow-sm">
    <h5 class="card-header">Activities Log</h5>
    
    <div class="table-responsive">
        @if($activityLogs->count() > 0)
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User ID</th>
                        <th>User Name</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activityLogs as $log)
                        <tr>
                            <td>
                                @if($log->user_id)
                                    <span class="badge bg-info">{{ $log->user_id }}</span>
                                @else
                                    <span class="badge bg-secondary">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($log->user)
                                    <strong>{{ $log->user->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $log->user->email }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->action === 'login')
                                    <span class="badge bg-success">{{ ucfirst($log->action) }}</span>
                                @elseif($log->action === 'logout')
                                    <span class="badge bg-warning">{{ ucfirst($log->action) }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($log->action) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log->description)
                                    {{ Str::limit($log->description, 50) }}
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-5 text-center">
                <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
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
@endsection

@section('scripts')
@endsection