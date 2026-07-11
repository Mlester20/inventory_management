@extends('layout.app')

@section('title', 'Categories')

@section('content')
    <div class="mt-3">
        <!-- Button trigger modal -->
        <div class="text-end">
            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#taxesModal"
            >
                Add Tax
            </button>
        </div>

        <!-- Add Tax Modal -->
        <div class="modal fade" id="taxesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('taxes.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Tax</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="tax_name" class="form-label">
                                    Tax Name
                                </label>
                                <input
                                    type="text"
                                    name="tax_name"
                                    id="tax_name"
                                    class="form-control"
                                    placeholder="e.g, Vatable"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="rate" class="form-label">
                                    Rate
                                </label>
                                <input
                                    type="text"
                                    name="rate"
                                    id="rate"
                                    class="form-control"
                                    placeholder="e.g., 12.5"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="add_is_active" class="form-label">
                                    Status
                                </label>
                                <select
                                    name="is_active"
                                    id="add_is_active"
                                    class="form-select"
                                    required
                                >
                                    <option value="1">Active</option>
                                    <option value="0" selected>Inactive</option>
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

        <!-- Update Tax Modal -->
        <div class="modal fade" id="updateTaxModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateTaxForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Tax</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_tax_name" class="form-label">
                                    Tax Name
                                </label>
                                <input
                                    type="text"
                                    name="tax_name"
                                    id="update_tax_name"
                                    class="form-control"
                                    placeholder="e.g, Vatable"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_rate" class="form-label">
                                    Rate
                                </label>
                                <input
                                    type="text"
                                    name="rate"
                                    id="update_rate"
                                    class="form-control"
                                    placeholder="e.g., 12.5"
                                    required
                                >
                            </div>
                            <div class="mb-3">
                                <label for="update_is_active" class="form-label">
                                    Status
                                </label>
                                <select
                                    name="is_active"
                                    id="update_is_active"
                                    class="form-select"
                                    required
                                >
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
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
        <h5 class="card-header">Manage Tax</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tax Name</th>
                        <th>Rate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($taxes as $tax)
                        <tr>
                            <td>{{ $tax->id }}</td>
                            <td>{{ $tax->name }}</td>
                            <td>{{ $tax->rate }}</td>
                            <td>
                                <span class="badge {{ $tax->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $tax->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateTaxModal"
                                    data-id="{{ $tax->id }}"
                                    data-name="{{ $tax->name }}"
                                    data-rate="{{ $tax->rate }}"
                                    data-active="{{ $tax->is_active ? '1' : '0' }}"
                                >
                                    Edit
                                </button>

                                <form
                                    action="{{ route('taxes.destroy', $tax) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this tax?')"
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
    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const taxId = this.getAttribute('data-id');
            const taxName = this.getAttribute('data-name');
            const rate = this.getAttribute('data-rate');
            const isActive = this.getAttribute('data-active');

            // Populate the modal form
            document.getElementById('update_tax_name').value = taxName;
            document.getElementById('update_rate').value = rate;
            document.getElementById('update_is_active').value = isActive;

            // Set the form action to the update route
            const form = document.getElementById('updateTaxForm');
            form.action = `/admin/taxes/${taxId}`;
        });
    });

    // Clear the add tax form when the modal is hidden
    const taxesModal = document.getElementById('taxesModal');
    taxesModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('tax_name').value = '';
        document.getElementById('rate').value = '';
        document.getElementById('add_is_active').value = '0';
    });

    // Clear the update tax form when the modal is hidden
    const updateTaxModal = document.getElementById('updateTaxModal');
    updateTaxModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('update_tax_name').value = '';
        document.getElementById('update_rate').value = '';
        document.getElementById('update_is_active').value = '1';
    });
</script>
@endsection