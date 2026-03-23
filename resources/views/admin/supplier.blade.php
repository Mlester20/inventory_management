@extends('layout.app')

@section('title', 'Categories')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#supplierModal"
        >
            Add Supplier
        </button>

        <!-- Add Supplier Modal -->
        <div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('suppliers.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Supplier</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="supplier_name" class="form-label">
                                    Supplier Name
                                </label>
                                <input
                                    type="text"
                                    name="supplier_name"
                                    id="supplier_name"
                                    class="form-control"
                                    placeholder="Enter supplier name"
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
                                    placeholder="Enter contact person"
                                    required
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
                                    placeholder="Enter email"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    Phone
                                </label>
                                <input
                                    type="number"
                                    maxlength="11"
                                    name="phone"
                                    id="phone"
                                    class="form-control"
                                    placeholder="Enter phone"
                                    required
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
                                    placeholder="Enter address"
                                    required
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

        <!-- View Supplier Modal -->
        <div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
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
                                <label><strong>Supplier Name:</strong></label>
                                <p id="view_supplier_name" class="text-muted"></p>
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
                                    Supplier Name
                                </label>
                                <input
                                    type="text"
                                    name="supplier_name"
                                    id="update_supplier_name"
                                    class="form-control"
                                    placeholder="Enter supplier name"
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
                                    placeholder="Enter contact person"
                                    required
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
                                    placeholder="Enter email"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_phone" class="form-label">
                                    Phone
                                </label>
                                <input
                                    type="number"
                                    maxlength="11"
                                    name="phone"
                                    id="update_phone"
                                    class="form-control"
                                    placeholder="Enter phone"
                                    required
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
                                    placeholder="Enter address"
                                    required
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
        <h5 class="card-header">Suppliers</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->id }}</td>
                            <td>{{ $supplier->supplier_name }}</td>
                            <td>{{ $supplier->contact_person }}</td>
                            <td>{{ $supplier->email }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewSupplierModal"
                                    data-id="{{ $supplier->id }}"
                                    data-name="{{ $supplier->supplier_name }}"
                                    data-contact="{{ $supplier->contact_person }}"
                                    data-phone="{{ $supplier->phone }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->address }}"
                                >
                                    View
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateSupplierModal"
                                    data-id="{{ $supplier->id }}"
                                    data-name="{{ $supplier->supplier_name }}"
                                    data-contact="{{ $supplier->contact_person }}"
                                    data-phone="{{ $supplier->phone }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->address }}"
                                >
                                    Edit
                                </button>

                                <form
                                    action="{{ route('suppliers.destroy', $supplier) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this supplier?')"
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
            const supplierId = this.getAttribute('data-id');
            const supplierName = this.getAttribute('data-name');
            const contactPerson = this.getAttribute('data-contact');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');
            
            // Populate the view modal
            document.getElementById('view_supplier_id').textContent = supplierId;
            document.getElementById('view_supplier_name').textContent = supplierName;
            document.getElementById('view_contact_person').textContent = contactPerson;
            document.getElementById('view_phone').textContent = phone;
            document.getElementById('view_email').textContent = email;
            document.getElementById('view_address').textContent = address;
        });
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const supplierId = this.getAttribute('data-id');
            const supplierName = this.getAttribute('data-name');
            const contactPerson = this.getAttribute('data-contact');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');
            
            // Populate the modal form
            document.getElementById('update_supplier_name').value = supplierName;
            document.getElementById('update_contact_person').value = contactPerson;
            document.getElementById('update_email').value = email;
            document.getElementById('update_phone').value = phone;
            document.getElementById('update_address').value = address;
            
            // Set the form action to the update route
            const form = document.getElementById('updateSupplierForm');
            form.action = `/admin/suppliers/${supplierId}`;
        });
    });

    // Clear the add supplier form when the modal is hidden
    const supplierModal = document.getElementById('supplierModal');
    supplierModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('supplier_name').value = '';
        document.getElementById('contact_person').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('address').value = '';
    });

    // Clear the update supplier form when the modal is hidden
    const updateSupplierModal = document.getElementById('updateSupplierModal');
    updateSupplierModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('update_supplier_name').value = '';
        document.getElementById('update_contact_person').value = '';
        document.getElementById('update_email').value = '';
        document.getElementById('update_phone').value = '';
        document.getElementById('update_address').value = '';
    });
</script>
@endsection