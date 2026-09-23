@extends('layout.app')

@section('title', 'Repack ' . $repack->reference)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('repacks.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Repacks
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Reference</label>
                    <p class="fw-bold mb-0">{{ $repack->reference }}</p>
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
