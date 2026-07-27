@php
    $invert = $invert ?? false;
    $upClass = $invert ? 'bg-label-danger' : 'bg-label-success';
    $downClass = $invert ? 'bg-label-success' : 'bg-label-danger';
@endphp

@if($trend['direction'] === 'up')
    <span class="badge {{ $upClass }}"><i class="bx bx-trending-up"></i> {{ number_format(abs($trend['percent']), 1) }}%</span>
@elseif($trend['direction'] === 'down')
    <span class="badge {{ $downClass }}"><i class="bx bx-trending-down"></i> {{ number_format(abs($trend['percent']), 1) }}%</span>
@elseif($trend['direction'] === 'flat')
    <span class="badge bg-label-secondary"><i class="bx bx-minus"></i> {{ number_format(abs($trend['percent']), 1) }}%</span>
@else
    <span class="badge bg-label-secondary">New</span>
@endif
