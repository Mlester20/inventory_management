{{--
    "* Returned by client" footnote for a Delivery Receipt or Sales Order.
    Params: $returned — rows of ['name' => string, 'qty' => int, 'refs' => string[]]
--}}
@if (! empty($returned))
    <div class="returned-note small mt-2">
        @foreach ($returned as $row)
            <strong>*</strong> {{ $row['qty'] }} {{ $row['qty'] === 1 ? 'unit' : 'units' }} Returned by Client: {{ $row['name'] }} ({{ implode(', ', array_unique($row['refs'])) }}){{ ! $loop->last ? ' ' : '' }}
        @endforeach
    </div>
@endif
