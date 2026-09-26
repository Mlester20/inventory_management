@extends('layout.app')

@section('title', 'Customer Types')

@section('content')
    <div class="mt-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerTypeModal">
            <i class="fas fa-plus"></i> Add Customer Type
        </button>

        <div class="form-text mt-2">
            These are the choices in the Customer Type drop-down of a customer.
            <strong>Walk-In</strong> (personal use) is built in and never has any withholding tax.
        </div>

        <!-- Add Customer Type Modal -->
        <div class="modal fade" id="customerTypeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('customer-types.store') }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Customer Type</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="customer_type_name" class="form-label">Customer Type</label>
                                <input type="text" name="name" id="customer_type_name" class="form-control" placeholder="e.g. Clinic" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Customer Type Modal -->
        <div class="modal fade" id="updateCustomerTypeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form id="updateCustomerTypeForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Customer Type</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_customer_type_name" class="form-label">Customer Type</label>
                                <input type="text" name="name" id="update_customer_type_name" class="form-control" required>
                                <div class="form-text">Customers with this type are renamed too.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Customer Types</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="text-end">Customers</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $type->name }}
                                @if($type->isProtected())
                                    <span class="badge bg-label-info">Built-in</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $type->customers_count }}</td>
                            <td>{{ $type->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                @if($type->isProtected())
                                    <span class="text-muted small">—</span>
                                @else
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                            <i class="bx bx-dots-vertical-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <button type="button" class="dropdown-item edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#updateCustomerTypeModal"
                                                data-id="{{ $type->id }}" data-name="{{ $type->name }}">
                                                <i class="bx bx-edit-alt me-1"></i> Edit
                                            </button>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ route('customer-types.destroy', $type) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"
                                                    onclick="return confirm('Are you sure you want to delete this customer type?')"
                                                    @if ($type->customers_count > 0) disabled title="Cannot delete: still in use" @endif>
                                                    <i class="bx bx-trash me-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No customer types yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.getElementById('customerTypeModal').addEventListener('hide.bs.modal', function () {
        document.getElementById('customer_type_name').value = '';
    });

    // Populate the edit modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('update_customer_type_name').value = this.getAttribute('data-name');
            document.getElementById('updateCustomerTypeForm').action = `/admin/customer-types/${this.getAttribute('data-id')}`;
        });
    });
</script>
@endsection
