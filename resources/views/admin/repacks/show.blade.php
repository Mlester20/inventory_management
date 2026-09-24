@extends('layout.app')

@section('title', 'Repack ' . $repack->reference)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('repacks.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Repacks
        </a>
        @if(! $repack->isVoided() && Auth::user()->role === 'admin')
            <form action="{{ route('repacks.void', $repack) }}" method="POST" class="d-flex gap-2"
                onsubmit="return confirmSubmit(this, 'Void {{ $repack->reference }}? The repacked pieces are taken back out and the source stock is returned. This only works while every piece is still there.');">
                @csrf
                <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Reason (optional)" maxlength="500" style="min-width: 220px;">
                <button type="submit" class="btn btn-outline-danger btn-sm text-nowrap">
                    <i class="bx bx-block"></i> Void Repack
                </button>
            </form>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($repack->isVoided())
        <div class="alert alert-secondary">
            <strong>Voided</strong> {{ $repack->voided_at?->format('M d, Y h:i A') }}
            @if($repack->voidedBy) by {{ $repack->voidedBy->name }} @endif
            @if($repack->void_reason) &mdash; {{ $repack->void_reason }} @endif
            <div class="small">The pieces were taken back out and the source stock was returned. Both movements are in Product History.</div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Reference</label>
                    <p class="fw-bold mb-0">{{ $repack->reference }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $repack->isVoided() ? 'danger' : 'success' }}">
                            {{ \App\Models\Repack::STATUSES[$repack->status] ?? $repack->status }}
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Date</label>
                    <p class="mb-0">{{ $repack->date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Location</label>
                    <p class="mb-0"><span class="badge bg-secondary">{{ $repack->location->name ?? '—' }}</span></p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $repack->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
            @if($repack->remarks)
                <div class="row mt-2">
                    <div class="col-12">
                        <label class="text-muted small">Remarks</label>
                        <p class="mb-0" style="white-space: pre-line;">{{ $repack->remarks }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Repacked Lines</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>From (Source)</th>
                        <th>Batch No.</th>
                        <th class="text-end">Qty Taken</th>
                        <th>To (Destination)</th>
                        <th>Batch No.</th>
                        <th class="text-end">Qty Produced</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($repack->lines as $line)
                        <tr>
                            <td>{{ $line->sourceBatch->product->item_name }}</td>
                            <td>{{ $line->sourceBatch->batch_no ?? '—' }}</td>
                            <td class="text-end">{{ $line->source_qty }}</td>
                            <td>{{ $line->destinationProduct->item_name }}</td>
                            <td>{{ $line->destinationBatch->batch_no ?? '—' }}</td>
                            <td class="text-end">{{ $line->destination_qty }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>
@endsection
