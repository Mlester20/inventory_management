@extends('layout.app')

@section('title', 'Customers')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#customerModal"
        >
            New Customer
        </button>

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
                                    placeholder="Enter customer name"
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
                                    placeholder="Enter delivery address (optional)"
                                    rows="3"
                                ></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_number" class="form-label">
                                    Contact Number
                                </label>
                                <input
                                    type="text"
                                    name="contact_number"
                                    id="contact_number"
                                    class="form-control"
                                    placeholder="Enter contact number (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    placeholder="Enter email (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">
                                    Contact Person
                                </label>
                                <input
                                    type="text"
                                    name="contact_person"
                                    id="contact_person"
                                    class="form-control"
                                    placeholder="Enter contact person (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="customer_type" class="form-label">
                                    Customer Type
                                </label>
                                <input
                                    type="text"
                                    name="customer_type"
                                    id="customer_type"
                                    class="form-control"
                                    placeholder="e.g. Pharmacy, Hospital, Clinic (optional)"
                                >
                            </div>
                            <div class="mb-3">
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
                            <div class="mb-3">
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
            <div class="modal-dialog" role="document">
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
                                    placeholder="Enter customer name"
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
                                    placeholder="Enter delivery address (optional)"
                                    rows="3"
                                ></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="update_contact_number" class="form-label">
                                    Contact Number
                                </label>
                                <input
                                    type="text"
                                    name="contact_number"
                                    id="update_contact_number"
                                    class="form-control"
                                    placeholder="Enter contact number (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_email" class="form-label">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    id="update_email"
                                    class="form-control"
                                    placeholder="Enter email (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_contact_person" class="form-label">
                                    Contact Person
                                </label>
                                <input
                                    type="text"
                                    name="contact_person"
                                    id="update_contact_person"
                                    class="form-control"
                                    placeholder="Enter contact person (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_customer_type" class="form-label">
                                    Customer Type
                                </label>
                                <input
                                    type="text"
                                    name="customer_type"
                                    id="update_customer_type"
                                    class="form-control"
                                    placeholder="e.g. Pharmacy, Hospital, Clinic (optional)"
                                >
                            </div>
                            <div class="mb-3">
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
                            <div class="mb-3">
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
                        <th class="text-end">Advances</th>
                        <th class="text-end">Balances</th>
                        <th class="text-end">Receivables (PHP)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->customer_name }}</td>
                            <td>{{ $customer->customer_type ?: '—' }}</td>
                            <td class="text-end">{{ $customer->sales_orders_count }}</td>
                            <td class="text-end">{{ $customer->sales_invoices_count }}</td>
                            <td class="text-end">{{ $customer->delivery_receipts_count }}</td>
                            <td class="text-end text-muted" title="No advances/payments ledger exists in this app yet">—</td>
                            <td class="text-end text-muted" title="No accounts-receivable ledger exists in this app yet">—</td>
                            <td class="text-end">{{ number_format($customer->receivables, 2) }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
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
                                >
                                    View
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
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
                                    Edit
                                </button>

                                <form
                                    action="{{ route('customers.destroy', $customer) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this customer?')"
                                    >
                                        Delete
                                    </button>
                                </form>
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
            document.getElementById('view_address').textContent = this.getAttribute('data-address') || 'N/A';
            document.getElementById('view_price_level').textContent = this.getAttribute('data-price-level') || 'N/A';
            document.getElementById('view_vat_type').textContent = this.getAttribute('data-vat-type') || 'N/A';
        });
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
