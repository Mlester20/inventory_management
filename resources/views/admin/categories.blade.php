@extends('layout.app')

@section('title', 'Generic Names')

@section('content')
    <div class="mt-3">
        <!-- Button triggers -->
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#genericNameModal"
        >
            Add Generic Name
        </button>

        <button
            type="button"
            class="btn btn-outline-secondary"
            data-bs-toggle="modal"
            data-bs-target="#categoryModal"
        >
            <i class="fas fa-plus"></i> Add Category
        </button>

        <!-- Add Generic Name Modal -->
        <div class="modal fade" id="genericNameModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('generic-names.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Generic Name</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="generic_name" class="form-label">
                                    Generic Name
                                </label>
                                <input
                                    type="text"
                                    name="generic_name"
                                    id="generic_name"
                                    class="form-control @error('generic_name') is-invalid @enderror"
                                    placeholder="Enter generic name"
                                    required
                                >
                                @error('generic_name')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">
                                    Category
                                </label>
                                <select
                                    name="category_id"
                                    id="category_id"
                                    class="form-select @error('category_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="unit" class="form-label">
                                    Unit
                                </label>
                                <input
                                    type="text"
                                    name="unit"
                                    id="unit"
                                    class="form-control @error('unit') is-invalid @enderror"
                                    placeholder="e.g. Tablet, Box, Bottle"
                                    required
                                >
                                @error('unit')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
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

        <!-- Update Generic Name Modal -->
        <div class="modal fade" id="updateGenericNameModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateGenericNameForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Generic Name</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_generic_name" class="form-label">
                                    Generic Name
                                </label>
                                <input
                                    type="text"
                                    name="generic_name"
                                    id="update_generic_name"
                                    class="form-control @error('generic_name') is-invalid @enderror"
                                    placeholder="Enter generic name"
                                    required
                                >
                                @error('generic_name')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_category_id" class="form-label">
                                    Category
                                </label>
                                <select
                                    name="category_id"
                                    id="update_category_id"
                                    class="form-select @error('category_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="update_unit" class="form-label">
                                    Unit
                                </label>
                                <input
                                    type="text"
                                    name="unit"
                                    id="update_unit"
                                    class="form-control @error('unit') is-invalid @enderror"
                                    placeholder="e.g. Tablet, Box, Bottle"
                                    required
                                >
                                @error('unit')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
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

        <!-- Add Category Modal -->
        <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form action="{{ route('categories.store') }}" method="POST">
                    @csrf

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Category</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="category_name" class="form-label">
                                    Category Name
                                </label>
                                <input
                                    type="text"
                                    name="category_name"
                                    id="category_name"
                                    class="form-control"
                                    placeholder="Enter category name"
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
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Generic Names</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Generic Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($genericNames as $genericName)
                        <tr>
                            <td>{{ $genericName->id }}</td>
                            <td>{{ $genericName->generic_name }}</td>
                            <td>{{ $genericName->category->category_name }}</td>
                            <td>{{ $genericName->unit }}</td>
                            <td>{{ $genericName->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-generic-name-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateGenericNameModal"
                                    data-id="{{ $genericName->id }}"
                                    data-name="{{ $genericName->generic_name }}"
                                    data-category="{{ $genericName->category_id }}"
                                    data-unit="{{ $genericName->unit }}"
                                >
                                    Edit
                                </button>

                                <form
                                    action="{{ route('generic-names.destroy', $genericName) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this generic name?')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No generic names yet. Create one to start adding products.
                            </td>
                        </tr>
                    @endforelse
            </table>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Categories</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td>{{ $category->category_name }}</td>
                            <td>{{ $category->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @endforeach
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.edit-generic-name-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const categoryId = this.getAttribute('data-category');
            const unit = this.getAttribute('data-unit');

            document.getElementById('update_generic_name').value = name;
            document.getElementById('update_category_id').value = categoryId;
            document.getElementById('update_unit').value = unit;

            // Set the form action to the update route
            const form = document.getElementById('updateGenericNameForm');
            form.action = `{{ route('generic-names.index') }}/${id}`;
        });
    });

    const genericNameModal = document.getElementById('genericNameModal');
    genericNameModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('generic_name').value = '';
        document.getElementById('category_id').value = '';
        document.getElementById('unit').value = '';
    });

    const categoryModal = document.getElementById('categoryModal');
    categoryModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('category_name').value = '';
    });
</script>
@endsection
