@extends('layout.app')

@section('title', 'Suppliers')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#supplierModal"
        >
            New Supplier
        </button>

        <!-- Add Supplier Modal -->
        <div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('suppliers.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">New Supplier</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="supplier_name" class="form-label">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    name="supplier_name"
                                    id="supplier_name"
                                    class="form-control"
                                    placeholder="e.g., MedSupply Philippines Inc."
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">
                                    Delivery address
                                </label>
                                <textarea
                                    name="delivery_address"
                                    id="delivery_address"
                                    class="form-control"
                                    placeholder="e.g., 123 Quirino Highway, Quezon City"
                                    rows="3"
                                    required
                                ></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_number" class="form-label">
                                        Contact Number
                                    </label>
                                    <input
                                        type="text"
                                        name="contact_number"
                                        id="contact_number"
                                        class="form-control"
                                        placeholder="e.g., 0917 123 4567"
                                        required
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        class="form-control"
                                        placeholder="e.g., sales@medsupplyph.com"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_person" class="form-label">
                                        Contact Person
                                    </label>
                                    <input
                                        type="text"
                                        name="contact_person"
                                        id="contact_person"
                                        class="form-control"
                                        placeholder="e.g., Juan Dela Cruz"
                                        required
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="vat_type" class="form-label">
                                        VAT Type
                                    </label>
                                    <select
                                        name="vat_type"
                                        id="vat_type"
                                        class="form-select"
                                        required
                                    >
                                        @foreach (\App\Models\Supplier::VAT_TYPES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Supplier Modal -->
        <div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Supplier Details</h5>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>ID:</strong></label>
                                <p id="view_supplier_id" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Name:</strong></label>
                                <p id="view_supplier_name" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Contact Person:</strong></label>
                                <p id="view_contact_person" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Contact Number:</strong></label>
                                <p id="view_contact_number" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Email:</strong></label>
                                <p id="view_email" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>VAT Type:</strong></label>
                                <p id="view_vat_type" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label><strong>Delivery address:</strong></label>
                                <p id="view_delivery_address" class="text-muted"></p>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="text-muted small d-block">GRNI</label>
                                <p id="view_grni" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Balance</label>
                                <p id="view_balance" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Payables</label>
                                <p id="view_payables" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Advance Payments</label>
                                <p id="view_advance_payments" class="fw-semibold mb-0 text-success"></p>
                            </div>
                        </div>

                        <label><strong>Recent Payments</strong> <span class="text-muted small">(latest 5)</span></label>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="view_payments_body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Record Payment Modal -->
        <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="recordPaymentForm" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Record Payment — <span id="payment_supplier_name"></span></h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <p class="text-muted small">Current balance: <span id="payment_current_balance" class="fw-semibold"></span></p>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_type" class="form-label">Type</label>
                                    <select name="type" id="payment_type" class="form-select" required>
                                        @foreach (\App\Models\SupplierPayment::TYPES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_amount" class="form-label">Amount</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="payment_amount" class="form-control" placeholder="e.g., 1500.00" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select name="payment_method" id="payment_method" class="form-select">
                                        <option value="">-- Select Method --</option>
                                        @foreach (\App\Models\SupplierPayment::PAYMENT_METHODS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="payment_remarks" class="form-label">Remarks</label>
                                <input type="text" name="remarks" id="payment_remarks" class="form-control" placeholder="e.g., OR #00123">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Save Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Update Supplier Modal -->
        <div class="modal fade" id="updateSupplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateSupplierForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Supplier</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_supplier_name" class="form-label">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    name="supplier_name"
                                    id="update_supplier_name"
                                    class="form-control"
                                    placeholder="e.g., MedSupply Philippines Inc."
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_delivery_address" class="form-label">
                                    Delivery address
                                </label>
                                <textarea
                                    name="delivery_address"
                                    id="update_delivery_address"
                                    class="form-control"
                                    placeholder="e.g., 123 Quirino Highway, Quezon City"
                                    rows="3"
                                    required
                                ></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="update_contact_number" class="form-label">
                                        Contact Number
                                    </label>
                                    <input
                                        type="text"
                                        name="contact_number"
                                        id="update_contact_number"
                                        class="form-control"
                                        placeholder="e.g., 0917 123 4567"
                                        required
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="update_email" class="form-label">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        name="email"
                                        id="update_email"
                                        class="form-control"
                                        placeholder="e.g., sales@medsupplyph.com"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="update_contact_person" class="form-label">
                                        Contact Person
                                    </label>
                                    <input
                                        type="text"
                                        name="contact_person"
                                        id="update_contact_person"
                                        class="form-control"
                                        placeholder="e.g., Juan Dela Cruz"
                                        required
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="update_vat_type" class="form-label">
                                        VAT Type
                                    </label>
                                    <select
                                        name="vat_type"
                                        id="update_vat_type"
                                        class="form-select"
                                        required
                                    >
                                        @foreach (\App\Models\Supplier::VAT_TYPES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>

                            <button type="submit" class="btn btn-primary">
                                Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Suppliers</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="text-end">Purchase Orders</th>
                        <th class="text-end">Purchase Invoices</th>
                        <th class="text-end">Goods Receipts</th>
                        <th class="text-end">
                            GRNI
                            <i class="bx bx-info-circle text-muted" style="cursor: help;" title="Goods Received Not Invoiced — Goods Receipts already in the Warehouse with no Purchase Invoice yet"></i>
                        </th>
                        <th class="text-end">Balances</th>
                        <th class="text-end">Payables (PHP)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->id }}</td>
                            <td>{{ $supplier->supplier_name }}</td>
                            <td class="text-end">{{ $supplier->purchase_orders_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('purchase-invoices.index', ['search' => $supplier->supplier_name]) }}">
                                    {{ $supplier->purchase_invoices_count }}
                                </a>
                            </td>
                            <td class="text-end">{{ $supplier->goods_receipts_count }}</td>
                            <td class="text-end">
                                @if($supplier->grni_count > 0)
                                    <span class="badge bg-label-info">{{ $supplier->grni_count }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end {{ $supplier->balance > 0 ? 'text-danger' : '' }}">{{ number_format($supplier->balance, 2) }}</td>
                            <td class="text-end">{{ number_format($supplier->payables, 2) }}</td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button
                                            type="button"
                                            class="dropdown-item payment-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#recordPaymentModal"
                                            data-id="{{ $supplier->id }}"
                                            data-name="{{ $supplier->supplier_name }}"
                                            data-balance="{{ number_format($supplier->balance, 2) }}"
                                        >
                                            <i class="bx bx-money me-1"></i> Payment
                                        </button>

                                        <button
                                            type="button"
                                            class="dropdown-item view-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewSupplierModal"
                                            data-id="{{ $supplier->id }}"
                                            data-name="{{ $supplier->supplier_name }}"
                                            data-contact="{{ $supplier->contact_person }}"
                                            data-contact-number="{{ $supplier->contact_number }}"
                                            data-email="{{ $supplier->email }}"
                                            data-address="{{ $supplier->delivery_address }}"
                                            data-vat-type="{{ $supplier->vat_type }}"
                                            data-grni="{{ $supplier->grni_count }}"
                                            data-advance-payments="{{ number_format($supplier->advance_payments, 2) }}"
                                            data-balance="{{ number_format($supplier->balance, 2) }}"
                                            data-payables="{{ number_format($supplier->payables, 2) }}"
                                            data-payments='{{ $supplier->payments->map(fn ($p) => [
                                                "date" => $p->payment_date->format("M d, Y"),
                                                "type" => \App\Models\SupplierPayment::TYPES[$p->type] ?? $p->type,
                                                "method" => $p->payment_method ?? "—",
                                                "amount" => number_format($p->amount, 2),
                                                "remarks" => $p->remarks ?? "—",
                                            ])->toJson() }}'
                                        >
                                            <i class="bx bx-show me-1"></i> View
                                        </button>

                                        <button
                                            type="button"
                                            class="dropdown-item edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#updateSupplierModal"
                                            data-id="{{ $supplier->id }}"
                                            data-name="{{ $supplier->supplier_name }}"
                                            data-contact="{{ $supplier->contact_person }}"
                                            data-contact-number="{{ $supplier->contact_number }}"
                                            data-email="{{ $supplier->email }}"
                                            data-address="{{ $supplier->delivery_address }}"
                                            data-vat-type="{{ $supplier->vat_type }}"
                                        >
                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                        </button>

                                        <div class="dropdown-divider"></div>

                                        <form
                                            action="{{ route('suppliers.destroy', $supplier) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('Are you sure you want to delete this supplier?')"
                                            >
                                                <i class="bx bx-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No suppliers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())
            <div class="card-footer">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    // Handle view button click to populate the view modal
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('view_supplier_id').textContent = this.getAttribute('data-id');
            document.getElementById('view_supplier_name').textContent = this.getAttribute('data-name');
            document.getElementById('view_contact_person').textContent = this.getAttribute('data-contact');
            document.getElementById('view_contact_number').textContent = this.getAttribute('data-contact-number');
            document.getElementById('view_email').textContent = this.getAttribute('data-email');
            document.getElementById('view_delivery_address').textContent = this.getAttribute('data-address');
            document.getElementById('view_vat_type').textContent = this.getAttribute('data-vat-type');

            document.getElementById('view_grni').textContent = this.getAttribute('data-grni') || '0';
            document.getElementById('view_advance_payments').textContent = '₱' + (this.getAttribute('data-advance-payments') || '0.00');
            document.getElementById('view_balance').textContent = '₱' + (this.getAttribute('data-balance') || '0.00');
            document.getElementById('view_payables').textContent = '₱' + (this.getAttribute('data-payables') || '0.00');

            const paymentsBody = document.getElementById('view_payments_body');
            paymentsBody.innerHTML = '';
            let payments = [];
            try {
                payments = JSON.parse(this.getAttribute('data-payments') || '[]');
            } catch (e) {
                payments = [];
            }

            if (payments.length === 0) {
                paymentsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payments recorded yet.</td></tr>';
            } else {
                payments.forEach(payment => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${payment.date}</td>
                        <td>${payment.type}</td>
                        <td>${payment.method}</td>
                        <td class="text-end">₱${payment.amount}</td>
                        <td>${payment.remarks}</td>
                    `;
                    paymentsBody.appendChild(row);
                });
            }
        });
    });

    // Handle payment button click to populate the record payment modal
    document.querySelectorAll('.payment-btn').forEach(button => {
        button.addEventListener('click', function() {
            const supplierId = this.getAttribute('data-id');
            document.getElementById('payment_supplier_name').textContent = this.getAttribute('data-name');
            document.getElementById('payment_current_balance').textContent = '₱' + this.getAttribute('data-balance');

            const form = document.getElementById('recordPaymentForm');
            form.action = `/admin/suppliers/${supplierId}/payments`;
        });
    });

    // Reset the record payment form when the modal is hidden
    const recordPaymentModal = document.getElementById('recordPaymentModal');
    recordPaymentModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('recordPaymentForm').reset();
        document.getElementById('payment_date').value = '{{ now()->toDateString() }}';
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const supplierId = this.getAttribute('data-id');

            document.getElementById('update_supplier_name').value = this.getAttribute('data-name');
            document.getElementById('update_contact_person').value = this.getAttribute('data-contact');
            document.getElementById('update_contact_number').value = this.getAttribute('data-contact-number');
            document.getElementById('update_email').value = this.getAttribute('data-email');
            document.getElementById('update_delivery_address').value = this.getAttribute('data-address');
            document.getElementById('update_vat_type').value = this.getAttribute('data-vat-type') || 'VAT';

            // Set the form action to the update route
            const form = document.getElementById('updateSupplierForm');
            form.action = `/admin/suppliers/${supplierId}`;
        });
    });

    // Clear the add supplier form when the modal is hidden
    const supplierModal = document.getElementById('supplierModal');
    supplierModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('supplier_name').value = '';
        document.getElementById('delivery_address').value = '';
        document.getElementById('contact_number').value = '';
        document.getElementById('email').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('vat_type').value = 'VAT';
    });
</script>
@endsection
