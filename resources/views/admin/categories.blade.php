@extends('layout.app')

@section('title', 'Categories')

@section('content')
    <div class="mt-3">
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#categoryModal"
        >
            <i class="fas fa-plus"></i> Add Category
        </button>

        <div class="form-text mt-2">
            Generic Item and Product management have moved to
            <a href="{{ route('inventory-items.index') }}">Products &amp; Inventory</a>.
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

        <!-- Edit Category Modal -->
        <div class="modal fade" id="updateCategoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">

                <form id="updateCategoryForm" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Edit Category</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_category_name" class="form-label">
                                    Category Name
                                </label>
                                <input
                                    type="text"
                                    name="category_name"
                                    id="update_category_name"
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
                                Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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
                        <th class="text-end">Generic Names</th>
                        <th class="text-end">Products</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td>{{ $category->category_name }}</td>
                            <td class="text-end">{{ $category->generic_names_count }}</td>
                            <td class="text-end">{{ $category->products_count }}</td>
                            <td>{{ $category->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateCategoryModal"
                                    data-id="{{ $category->id }}"
                                    data-name="{{ $category->category_name }}"
                                >
                                    Edit
                                </button>

                                <form
                                    action="{{ route('categories.destroy', $category) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    @php
                                        $categoryInUse = $category->generic_names_count > 0 || $category->products_count > 0;
                                    @endphp
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this category?')"
                                        @if ($categoryInUse) disabled title="Cannot delete: still in use" @endif
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No categories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
            <div class="card-footer">
                {{ $categories->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    const categoryModal = document.getElementById('categoryModal');
    categoryModal.addEventListener('hide.bs.modal', function() {
        document.getElementById('category_name').value = '';
    });

    // Handle edit button click to populate the update modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-id');

            document.getElementById('update_category_name').value = this.getAttribute('data-name');

            const form = document.getElementById('updateCategoryForm');
            form.action = `/admin/categories/${categoryId}`;
        });
    });
</script>
@endsection
