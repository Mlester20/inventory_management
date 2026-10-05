@extends('layout.app')

@section('title', 'Repacks')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('repacks.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by reference"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('repacks.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('repacks.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Repack
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Repacks</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Lines</th>
                        <th>Prepared By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($repacks as $repack)
                        <tr>
                            <td>{{ $repack->id }}</td>
                            <td>{{ $repack->reference }}</td>
                            <td>
                                <span class="badge bg-{{ $repack->isDraft() ? 'warning' : ($repack->isVoided() ? 'danger' : 'success') }}">
                                    {{ \App\Models\Repack::STATUSES[$repack->status] ?? $repack->status }}
                                </span>
                            </td>
                            <td>{{ $repack->date?->format('M d, Y') ?? '—' }}</td>
                            <td>{{ $repack->location->name ?? '—' }}</td>
                            <td>{{ $repack->lines()->count() }}</td>
                            <td>{{ $repack->preparedBy->name ?? '—' }}</td>
                            <td>
                                <a href="{{ route('repacks.show', $repack) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No Repacks found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($repacks->hasPages())
            <div class="card-footer">
                {{ $repacks->links() }}
            </div>
        @endif
    </div>
@endsection
