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
                                    <span class="badge bg-warning ">{{ ucfirst($log->action) }}</span>
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

    @if($activityLogs->count() > 0)
        <div class="card-footer bg-light d-flex justify-content-center">
            {{ $activityLogs->links() }}
        </div>
    @endif
</div>

@endsection

@section('scripts')
@endsection