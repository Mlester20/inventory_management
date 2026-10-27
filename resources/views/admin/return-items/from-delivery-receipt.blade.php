@extends('layout.app')

@section('title', 'New Product Return')

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">New Product Return</h5>
            <a href="{{ route('return-items.create') }}" class="small">Other return (POS / direct invoice)</a>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-muted small">
                Pick the customer and the Delivery Receipt the goods came from. Every item on it is listed — enter a quantity (and the reason)
                only for what is coming back.
            </p>

            <form action="{{ route('return-items.store-from-dr') }}" method="POST" id="fromDrForm">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="customer_search" class="form-label">Customer</label>
                        <input type="text" id="customer_search" class="form-control" list="customer_datalist" autocomplete="off"
                               placeholder="Search customer..." value="{{ $preselected?->customer?->customer_name }}">
                        <datalist id="customer_datalist">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->customer_name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="drSelect" class="form-label">Delivery Receipt</label>
                        <select name="delivery_receipt_id" id="drSelect" class="form-select" data-selected="{{ old('delivery_receipt_id', $preselected?->id) }}" disabled>
                            <option value="">-- Pick a customer first --</option>
                        </select>
                        <div class="form-text" id="drHint"></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        @include('admin.partials.prepared-by-select', ['selected' => auth()->id()])
                    </div>
                </div>

                <div id="linesCard" class="d-none">
                    <div class="alert alert-info py-2 small mb-2" id="refundNote"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Items on this Delivery Receipt</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="fillAllBtn">Return everything that can still be returned</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Description</th>
                                    <th>Lot/Batch</th>
                                    <th class="text-end">Delivered</th>
                                    <th class="text-end">Already returned</th>
                                    <th class="text-end">Can return</th>
                                    <th style="width: 100px;">Return Qty</th>
                                    <th style="min-width: 250px;">Reason</th>
                                    <th style="min-width: 170px;">Notes</th>
                                </tr>
                            </thead>
                            <tbody id="linesBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" id="saveBtn" disabled
                        @disabled(Auth::user()->role !== 'admin')
                        @if(Auth::user()->role !== 'admin') title="Submitting is restricted to full admin accounts." @endif>
                        Save Product Return
                    </button>
                    <a href="{{ route('return-items.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const CUSTOMERS = @json($customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->customer_name])->values());
    const REASONS = @json($reasons);
    const NO_LOT = '(no lot)';

    const customerInput = document.getElementById('customer_search');
    const drSelect = document.getElementById('drSelect');
    const drHint = document.getElementById('drHint');
    const linesCard = document.getElementById('linesCard');
    const linesBody = document.getElementById('linesBody');
    const refundNote = document.getElementById('refundNote');
    const saveBtn = document.getElementById('saveBtn');

    let deliveries = [];
    let loadToken = 0;   // an older, slower answer must not overwrite a newer one

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function renderLines() {
        const dr = deliveries.find(d => String(d.id) === String(drSelect.value));
        linesBody.innerHTML = '';
        linesCard.classList.toggle('d-none', !dr);
        saveBtn.disabled = !dr || {{ Auth::user()->role !== 'admin' ? 'true' : 'false' }};
        if (!dr) return;

        refundNote.textContent = dr.invoiced
            ? 'This Delivery Receipt already has an invoice, so what is returned is refunded (cash or store credit) when you approve it. The stock goes back to the Warehouse.'
            : 'This Delivery Receipt has no invoice yet, so there is no refund — the stock goes back to the Warehouse and the items cannot be invoiced anymore.';

        dr.rows.forEach(row => {
            const tr = document.createElement('tr');
            const disabled = row.returnable <= 0 ? 'disabled' : '';
            tr.innerHTML = `
                <td>${escapeHtml(row.name)}</td>
                <td>${escapeHtml(row.batch_no || NO_LOT)}${row.expiration_date ? '<div class="small text-muted">exp ' + escapeHtml(row.expiration_date) + '</div>' : ''}</td>
                <td class="text-end">${row.delivered} ${escapeHtml(row.unit)}</td>
                <td class="text-end">${row.returned}</td>
                <td class="text-end fw-semibold">${row.returnable}</td>
                <td><input type="number" class="form-control form-control-sm qty-input" name="lines[${row.batch_id}][qty]" min="0" max="${row.returnable}" value="0" ${disabled}></td>
                <td>
                    <select class="form-select form-select-sm reason-input" name="lines[${row.batch_id}][reason]" ${disabled}>
                        <option value="">-- Reason --</option>
                        ${REASONS.map(r => `<option value="${escapeHtml(r)}">${escapeHtml(r)}</option>`).join('')}
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm" name="lines[${row.batch_id}][notes]" maxlength="500" placeholder="Optional" ${disabled}></td>`;
            linesBody.appendChild(tr);
        });
    }

    async function loadDeliveries(customer) {
        const token = ++loadToken;
        deliveries = [];
        drSelect.innerHTML = '';
        drSelect.disabled = true;
        drHint.textContent = '';

        if (!customer) {
            drSelect.add(new Option('-- Pick a customer first --', ''));
            renderLines();
            return;
        }

        drSelect.add(new Option('Loading...', ''));
        const res = await fetch(`/api/customers/${customer.id}/returnable-deliveries`);
        const data = (await res.json()).deliveries || [];
        if (token !== loadToken) return;
        deliveries = data;
        drSelect.innerHTML = '';

        if (deliveries.length === 0) {
            drSelect.add(new Option('Nothing left to return', ''));
            drHint.textContent = 'This customer has no posted Delivery Receipt with items that can still be returned.';
            renderLines();
            return;
        }

        if (deliveries.length > 1) {
            drSelect.add(new Option('-- Select the Delivery Receipt --', ''));
        }
        deliveries.forEach(d => {
            drSelect.add(new Option(`${d.dr_no}${d.so_no ? ' · ' + d.so_no : ''} — ${d.date}${d.invoiced ? ' (invoiced)' : ''}`, d.id));
        });
        drSelect.disabled = false;

        const wanted = drSelect.dataset.selected;
        if (wanted && deliveries.some(d => String(d.id) === String(wanted))) {
            drSelect.value = wanted;
        } else if (deliveries.length === 1) {
            drSelect.value = deliveries[0].id;
        }
        renderLines();
    }

    customerInput.addEventListener('input', function () {
        const match = CUSTOMERS.find(c => c.name === this.value) || null;
        drSelect.dataset.selected = '';
        loadDeliveries(match);
    });

    drSelect.addEventListener('change', renderLines);

    document.getElementById('fillAllBtn').addEventListener('click', function () {
        linesBody.querySelectorAll('.qty-input:not(:disabled)').forEach(input => {
            input.value = input.max;
        });
    });

    document.getElementById('fromDrForm').addEventListener('submit', function (e) {
        const rows = [...linesBody.querySelectorAll('tr')];
        const picked = rows.filter(tr => Number(tr.querySelector('.qty-input').value) > 0);

        if (picked.length === 0) {
            e.preventDefault();
            alert('Enter a return quantity for at least one item.');
            return;
        }

        const missing = picked.find(tr => !tr.querySelector('.reason-input').value);
        if (missing) {
            e.preventDefault();
            alert('Choose a reason for every item you are returning.');
            missing.querySelector('.reason-input').focus();
        }
    });

    // Opened from a Delivery Receipt: the customer and the receipt are already known.
    const startCustomer = CUSTOMERS.find(c => c.name === customerInput.value) || null;
    if (startCustomer) {
        loadDeliveries(startCustomer);
    }
</script>
@endsection
