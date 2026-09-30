{{--
    Shared "Qty" filter, offered on both the General Item and Products views (Sir asked for the
    same filter on both). $tab is passed in so the link targets the right view.
--}}
<div class="dropdown">
    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bx bx-filter-alt me-1"></i>
        Qty: {{ $qtyFilter === 'hide_zero' ? 'Hide 0' : ($qtyFilter === 'only_zero' ? 'Only 0' : 'All') }}
    </button>
    <div class="dropdown-menu">
        <a class="dropdown-item {{ ! $qtyFilter ? 'active' : '' }}"
           href="{{ route('inventory-items.index', array_merge(request()->query(), ['tab' => $tab, 'qty_filter' => null])) }}">
            All quantities
        </a>
        <a class="dropdown-item {{ $qtyFilter === 'hide_zero' ? 'active' : '' }}"
           href="{{ route('inventory-items.index', array_merge(request()->query(), ['tab' => $tab, 'qty_filter' => 'hide_zero'])) }}">
            Hide 0 Qty
        </a>
        <a class="dropdown-item {{ $qtyFilter === 'only_zero' ? 'active' : '' }}"
           href="{{ route('inventory-items.index', array_merge(request()->query(), ['tab' => $tab, 'qty_filter' => 'only_zero'])) }}">
            Show only 0 Qty
        </a>
    </div>
</div>
