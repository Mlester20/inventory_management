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
                            <td class="text-end text-muted" title="No Purchase Invoice module exists in this app yet">—</td>
                            <td class="text-end">{{ $supplier->goods_receipts_count }}</td>
                            <td class="text-end text-muted" title="No accounts-payable ledger exists in this app yet">—</td>
                            <td class="text-end">{{ number_format($supplier->payables, 2) }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info view-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewSupplierModal"
                                    data-id="{{ $supplier->id }}"
                                    data-name="{{ $supplier->supplier_name }}"
                                    data-contact="{{ $supplier->contact_person }}"
                                    data-contact-number="{{ $supplier->contact_number }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->delivery_address }}"
                                    data-vat-type="{{ $supplier->vat_type }}"
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
                                    data-contact-number="{{ $supplier->contact_number }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->delivery_address }}"
                                    data-vat-type="{{ $supplier->vat_type }}"
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
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No suppliers yet.</td>
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
            document.getElementById('view_address').textContent = this.getAttribute('data-address');
            document.getElementById('view_vat_type').textContent = this.getAttribute('data-vat-type');
        });
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
