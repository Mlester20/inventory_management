{{--
    Shared 3-column signature row. Labels default to the common
    Prepared/Approved/Received set but can be overridden per document
    (e.g. Goods Receipt wants "Inspected By" instead of "Approved By").

    Params (all optional):
    - label1 / value1 (default: "Prepared By" / preparedBy name)
    - label2 / value2 (default: "Approved By" / blank line)
    - label3 / value3 (default: "Received By" / blank line)
    - columns (1, 2, or 3, default 3) — some documents only need one or two signers
--}}
@php
    $columns = $columns ?? 3;
    $colClass = match ($columns) {
        1 => 'col-4',
        2 => 'col-6',
        default => 'col-4',
    };
@endphp
<div class="row mt-4 signature-block">
    <div class="{{ $colClass }} text-center">
        <div class="border-top pt-1">{{ $label1 ?? 'Prepared By' }}: {{ $value1 ?? '—' }}</div>
    </div>
    @if($columns >= 2)
        <div class="{{ $colClass }} text-center">
            <div class="border-top pt-1">{{ $label2 ?? 'Approved By' }}: {{ $value2 ?? '____________________' }}</div>
        </div>
    @endif
    @if($columns === 3)
        <div class="{{ $colClass }} text-center">
            <div class="border-top pt-1">{{ $label3 ?? 'Received By' }}: {{ $value3 ?? '____________________' }}</div>
        </div>
    @endif
</div>
