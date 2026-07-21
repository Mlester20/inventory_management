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
            Add Customer
        </button>

        <!-- Add Customer Modal -->
        <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('customers.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Customer</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">
                                    Customer Name
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
                                <label for="phone" class="form-label">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="phone"
                                    class="form-control"
                                    placeholder="Enter phone (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">
                                    Address
                                </label>
                                <input
                                    type="text"
                                    name="address"
                                    id="address"
                                    class="form-control"
                                    placeholder="Enter address (optional)"
                                >
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
                                <label><strong>Customer Name:</strong></label>
                                <p id="view_customer_name" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Contact Person:</strong></label>
                                <p id="view_contact_person" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <label><strong>Phone:</strong></label>
                                <p id="view_phone" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label><strong>Email:</strong></label>
                                <p id="view_email" class="text-muted"></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label><strong>Address:</strong></label>
                                <p id="view_address" class="text-muted"></p>
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
                                    Customer Name
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
                                <label for="update_phone" class="form-label">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="update_phone"
                                    class="form-control"
                                    placeholder="Enter phone (optional)"
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_address" class="form-label">
                                    Address
                                </label>
                                <input
                                    type="text"
                                    name="address"
                                    id="update_address"
                                    class="form-control"
                                    placeholder="Enter address (optional)"
                                >
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
                        <th>Customer Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->customer_name }}</td>
                            <td>{{ $customer->contact_person }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewCustomerModal"
                                    data-id="{{ $customer->id }}"
                                    data-name="{{ $customer->customer_name }}"
                                    data-contact="{{ $customer->contact_person }}"
                                    data-phone="{{ $customer->phone }}"
                                    data-email="{{ $customer->email }}"
                                    data-address="{{ $customer->address }}"
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
                                    data-contact="{{ $customer->contact_person }}"
                                    data-phone="{{ $customer->phone }}"
                                    data-email="{{ $customer->email }}"
                                    data-address="{{ $customer->address }}"
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
                    @endforeach
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
            const customerId = this.getAttribute('data-id');
            const customerName = this.getAttribute('data-name');
            const contactPerson = this.getAttribute('data-contact');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');

            // Populate the view modal
            document.getElementById('view_customer_id').textContent = customerId;
            document.getElementById('view_customer_name').textContent = customerName;
            document.getElementById('view_contact_person').textContent = contactPerson || 'N/A';
            document.getElementById('view_phone').textContent = phone || 'N/A';
            document.getElementById('view_email').textContent = email || 'N/A';
            document.getElementById('view_address').textContent = address || 'N/A';
        });
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const customerId = this.getAttribute('data-id');
            const customerName = this.getAttribute('data-name');
            const contactPerson = this.getAttribute('data-contact');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');

            // Populate the modal form
            document.getElementById('update_customer_name').value = customerName;
            document.getElementById('update_contact_person').value = contactPerson || '';
            document.getElementById('update_email').value = email || '';
            document.getElementById('update_phone').value = phone || '';
            document.getElementById('update_address').value = address || '';

            // Set the form action to the update route
            const form = document.getElementById('updateCustomerForm');
            form.action = `/admin/customers/${customerId}`;
        });
    });

    // Clear the add customer form when the modal is hidden
    const customerModal = document.getElementById('customerModal');
    customerModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('customer_name').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('address').value = '';
    });

    // Clear the update customer form when the modal is hidden
    const updateCustomerModal = document.getElementById('updateCustomerModal');
    updateCustomerModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('update_customer_name').value = '';
        document.getElementById('update_contact_person').value = '';
        document.getElementById('update_email').value = '';
        document.getElementById('update_phone').value = '';
        document.getElementById('update_address').value = '';
    });
</script>
@endsection
