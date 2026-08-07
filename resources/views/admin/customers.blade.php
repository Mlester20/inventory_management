@extends(Auth::user()->role === 'admin' ? 'layout.app' : 'layout.user')

@section('title', 'Customers')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <div class="text-end">
            <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#customerModal"
            >
                New Customer
            </button>
        </div>

        <!-- Add Customer Modal -->
        <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('customers.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">New Customer</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    name="customer_name"
                                    id="customer_name"
                                    class="form-control"
                                    placeholder="e.g., Green Cross Pharmacy"
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
                                    placeholder="e.g., 123 Rizal St., Quezon City (optional)"
                                    rows="3"
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
                                        placeholder="e.g., 0917 123 4567 (optional)"
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
                                        placeholder="e.g., contact@pharmacy.com (optional)"
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
                                        placeholder="e.g., Juan Dela Cruz (optional)"
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="customer_type" class="form-label">
                                        Customer Type
                                    </label>
                                    <input
                                        type="text"
                                        name="customer_type"
                                        id="customer_type"
                                        class="form-control"
                                        placeholder="e.g. Pharmacy, Hospital, Clinic"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price_level" class="form-label">
                                        Price Level
                                    </label>
                                    <select
                                        name="price_level"
                                        id="price_level"
                                        class="form-select"
                                        required
                                    >
                                        @foreach (\App\Models\Customer::PRICE_LEVELS as $value => $label)
                                            <option value="{{ $value }}" {{ $value === 'retail' ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
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
                                        @foreach (\App\Models\Customer::VAT_TYPES as $value => $label)
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

        <!-- View Customer Modal -->
        <div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Customer Details</h5>
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
                                <p id="view_customer_id" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Name:</strong></label>
                                <p id="view_customer_name" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Customer Type:</strong></label>
                                <p id="view_customer_type" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Contact Person:</strong></label>
                                <p id="view_contact_person" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Contact Number:</strong></label>
                                <p id="view_contact_number" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Email:</strong></label>
                                <p id="view_email" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Price Level:</strong></label>
                                <p id="view_price_level" class="text-muted"></p>
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
                                <label class="text-muted small d-block">Advances</label>
                                <p id="view_advances" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Balances</label>
                                <p id="view_balance" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Receivables</label>
                                <p id="view_receivables" class="fw-semibold mb-0"></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Available Credit</label>
                                <p id="view_available_credit" class="fw-semibold mb-0 text-success"></p>
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
                            <h5 class="modal-title">Record Payment — <span id="payment_customer_name"></span></h5>
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
                                        @foreach (\App\Models\CustomerPayment::TYPES as $value => $label)
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
                                        @foreach (\App\Models\CustomerPayment::PAYMENT_METHODS as $value => $label)
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

        <!-- Update Customer Modal -->
        <div class="modal fade" id="updateCustomerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateCustomerForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Customer</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_customer_name" class="form-label">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    name="customer_name"
                                    id="update_customer_name"
                                    class="form-control"
                                    placeholder="e.g., Green Cross Pharmacy"
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
                                    placeholder="e.g., 123 Rizal St., Quezon City (optional)"
                                    rows="3"
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
                                        placeholder="e.g., 0917 123 4567 (optional)"
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
                                        placeholder="e.g., contact@pharmacy.com (optional)"
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
                                        placeholder="e.g., Juan Dela Cruz (optional)"
                                    >
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="update_customer_type" class="form-label">
                                        Customer Type
                                    </label>
                                    <input
                                        type="text"
                                        name="customer_type"
                                        id="update_customer_type"
                                        class="form-control"
                                        placeholder="e.g. Pharmacy, Hospital, Clinic"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="update_price_level" class="form-label">
                                        Price Level
                                    </label>
                                    <select
                                        name="price_level"
                                        id="update_price_level"
                                        class="form-select"
                                        required
                                    >
                                        @foreach (\App\Models\Customer::PRICE_LEVELS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
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
                                        @foreach (\App\Models\Customer::VAT_TYPES as $value => $label)
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
        <h5 class="card-header">Customers</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Customer Type</th>
                        <th class="text-end">Sales Orders</th>
                        <th class="text-end">Sales Invoices</th>
                        <th class="text-end">Delivery Notes</th>
                        <th class="text-end">
                            Advances
                            <i class="bx bx-info-circle text-muted" style="cursor: help;" title="Delivery Receipts made without a Purchase Order (Advance Order type)"></i>
                        </th>
                        <th class="text-end">
                            Balances
                            <i class="bx bx-info-circle text-muted" style="cursor: help;" title="Invoices this customer still owes money on"></i>
                        </th>
                        <th class="text-end">Receivables (PHP)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->customer_name }}</td>
                            <td>
                                @if($customer->customer_type)
                                    <span class="badge bg-label-secondary">{{ Str::title(str_replace('_', ' ', $customer->customer_type)) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $customer->sales_orders_count }}</td>
                            <td class="text-end">{{ $customer->sales_invoices_count }}</td>
                            <td class="text-end">{{ $customer->delivery_receipts_count }}</td>
                            <td class="text-end">
                                @if($customer->advances_count > 0)
                                    <span class="badge bg-label-info">{{ $customer->advances_count }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($customer->balances_count > 0)
                                    <span class="badge bg-label-danger">{{ $customer->balances_count }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">₱{{ number_format($customer->receivables, 2) }}</td>
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
                                            data-id="{{ $customer->id }}"
                                            data-name="{{ $customer->customer_name }}"
                                            data-balance="{{ number_format($customer->receivables, 2) }}"
                                        >
                                            <i class="bx bx-money me-1"></i> Payment
                                        </button>

                                        <button
                                            type="button"
                                            class="dropdown-item view-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewCustomerModal"
                                            data-id="{{ $customer->id }}"
                                            data-name="{{ $customer->customer_name }}"
                                            data-type="{{ $customer->customer_type }}"
                                            data-contact="{{ $customer->contact_person }}"
                                            data-contact-number="{{ $customer->contact_number }}"
                                            data-email="{{ $customer->email }}"
                                            data-address="{{ $customer->delivery_address }}"
                                            data-price-level="{{ \App\Models\Customer::PRICE_LEVELS[$customer->price_level] ?? $customer->price_level }}"
                                            data-vat-type="{{ $customer->vat_type }}"
                                            data-advances="{{ $customer->advances_count }}"
                                            data-balance="{{ $customer->balances_count }}"
                                            data-receivables="{{ number_format($customer->receivables, 2) }}"
                                            data-available-credit="{{ number_format($customer->available_credit, 2) }}"
                                            data-payments='{{ $customer->payments->map(fn ($p) => [
                                                "date" => $p->payment_date->format("M d, Y"),
                                                "type" => \App\Models\CustomerPayment::TYPES[$p->type] ?? $p->type,
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
                                            data-bs-target="#updateCustomerModal"
                                            data-id="{{ $customer->id }}"
                                            data-name="{{ $customer->customer_name }}"
                                            data-type="{{ $customer->customer_type }}"
                                            data-contact="{{ $customer->contact_person }}"
                                            data-contact-number="{{ $customer->contact_number }}"
                                            data-email="{{ $customer->email }}"
                                            data-address="{{ $customer->delivery_address }}"
                                            data-price-level="{{ $customer->price_level }}"
                                            data-vat-type="{{ $customer->vat_type }}"
                                        >
                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                        </button>

                                        <div class="dropdown-divider"></div>

                                        <form
                                            action="{{ route('customers.destroy', $customer) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('Are you sure you want to delete this customer?')"
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
                            <td colspan="10" class="text-center text-muted py-4">No customers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())
            <div class="card-footer">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    // Handle view button click to populate the view modal
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('view_customer_id').textContent = this.getAttribute('data-id');
            document.getElementById('view_customer_name').textContent = this.getAttribute('data-name');
            document.getElementById('view_customer_type').textContent = this.getAttribute('data-type') || 'N/A';
            document.getElementById('view_contact_person').textContent = this.getAttribute('data-contact') || 'N/A';
            document.getElementById('view_contact_number').textContent = this.getAttribute('data-contact-number') || 'N/A';
            document.getElementById('view_email').textContent = this.getAttribute('data-email') || 'N/A';
            document.getElementById('view_delivery_address').textContent = this.getAttribute('data-address') || 'N/A';
            document.getElementById('view_price_level').textContent = this.getAttribute('data-price-level') || 'N/A';
            document.getElementById('view_vat_type').textContent = this.getAttribute('data-vat-type') || 'N/A';

            document.getElementById('view_advances').textContent = this.getAttribute('data-advances') || '0';
            document.getElementById('view_balance').textContent = this.getAttribute('data-balance') || '0';
            document.getElementById('view_receivables').textContent = '₱' + (this.getAttribute('data-receivables') || '0.00');
            document.getElementById('view_available_credit').textContent = '₱' + (this.getAttribute('data-available-credit') || '0.00');

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
            const customerId = this.getAttribute('data-id');
            document.getElementById('payment_customer_name').textContent = this.getAttribute('data-name');
            document.getElementById('payment_current_balance').textContent = '₱' + this.getAttribute('data-balance');

            const form = document.getElementById('recordPaymentForm');
            form.action = `/admin/customers/${customerId}/payments`;
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
            const customerId = this.getAttribute('data-id');

            document.getElementById('update_customer_name').value = this.getAttribute('data-name');
            document.getElementById('update_customer_type').value = this.getAttribute('data-type') || '';
            document.getElementById('update_contact_person').value = this.getAttribute('data-contact') || '';
            document.getElementById('update_contact_number').value = this.getAttribute('data-contact-number') || '';
            document.getElementById('update_email').value = this.getAttribute('data-email') || '';
            document.getElementById('update_delivery_address').value = this.getAttribute('data-address') || '';
            document.getElementById('update_price_level').value = this.getAttribute('data-price-level') || 'retail';
            document.getElementById('update_vat_type').value = this.getAttribute('data-vat-type') || 'VAT';

            // Set the form action to the update route
            const form = document.getElementById('updateCustomerForm');
            form.action = `/admin/customers/${customerId}`;
        });
    });

    // Clear the add customer form when the modal is hidden
    const customerModal = document.getElementById('customerModal');
    customerModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('customer_name').value = '';
        document.getElementById('delivery_address').value = '';
        document.getElementById('contact_number').value = '';
        document.getElementById('email').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('customer_type').value = '';
        document.getElementById('price_level').value = 'retail';
        document.getElementById('vat_type').value = 'VAT';
    });
</script>
@endsection