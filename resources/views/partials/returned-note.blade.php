{{--
    "* Returned by client" footnote for a Delivery Receipt or Sales Order.
    Params: $returned — rows of ['name' => string, 'qty' => int, 'refs' => string[]]
--}}
@if (! empty($returned))
    <div class="returned-note small mt-2">
        <strong>*</strong> Returned by client:
        @foreach ($returned as $row)
            {{ $row['name'] }} — {{ $row['qty'] }} ({{ implode(', ', array_unique($row['refs'])) }}){{ ! $loop->last ? ';' : '' }}
        @endforeach
    </div>
@endif
