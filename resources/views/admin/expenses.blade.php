@extends('layout.app')

@section('title', 'Expenses')

@section('content')
    <div class="mt-3 d-flex justify-content-end gap-2">
        <button
            type="button"
            class="btn btn-outline-secondary"
            data-bs-toggle="modal"
            data-bs-target="#manageCategoriesModal"
        >
            Manage Categories
        </button>
        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#expenseModal"
        >
            Add Expense
        </button>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="expense_category_id" class="form-label">Category</label>
                            <select name="expense_category_id" id="expense_category_id" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="expense_date" class="form-label">Date</label>
                            <input type="date" name="expense_date" id="expense_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="paid_to" class="form-label">Paid To</label>
                            <input type="text" name="paid_to" id="paid_to" class="form-control" placeholder="e.g., Meralco">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" name="description" id="description" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="prepared_by" class="form-label">Prepared By</label>
                            <select name="prepared_by" id="prepared_by" class="form-select">
                                <option value="">-- Select User --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" {{ auth()->id() == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
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

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="updateExpenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="updateExpenseForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="update_expense_category_id" class="form-label">Category</label>
                            <select name="expense_category_id" id="update_expense_category_id" class="form-select" required>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="update_expense_date" class="form-label">Date</label>
                            <input type="date" name="expense_date" id="update_expense_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="update_amount" class="form-label">Amount</label>
                            <input type="number" name="amount" id="update_amount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="update_paid_to" class="form-label">Paid To</label>
                            <input type="text" name="paid_to" id="update_paid_to" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="update_description" class="form-label">Description</label>
                            <input type="text" name="description" id="update_description" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="update_prepared_by" class="form-label">Prepared By</label>
                            <select name="prepared_by" id="update_prepared_by" class="form-select">
                                <option value="">-- Select User --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
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

    <!-- Manage Categories Modal -->
    <div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Expense Categories</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group mb-3">
                        @forelse ($expenseCategories as $category)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ $category->name }}
                                <form action="{{ route('expense-categories.destroy', $category) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this category?')"
                                    >
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No categories yet.</li>
                        @endforelse
                    </ul>
                    <form action="{{ route('expense-categories.store') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control" placeholder="New category name" required>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Expenses</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th class="text-end">Amount</th>
                        <th>Paid To</th>
                        <th>Description</th>
                        <th>Prepared By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->id }}</td>
                            <td>{{ $expense->expense_date->format('M d, Y') }}</td>
                            <td>{{ $expense->category->name }}</td>
                            <td class="text-end">{{ number_format($expense->amount, 2) }}</td>
                            <td>{{ $expense->paid_to ?? '—' }}</td>
                            <td>{{ $expense->description ?? '—' }}</td>
                            <td>{{ $expense->preparedBy->name ?? '—' }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#updateExpenseModal"
                                    data-id="{{ $expense->id }}"
                                    data-category-id="{{ $expense->expense_category_id }}"
                                    data-date="{{ $expense->expense_date->toDateString() }}"
                                    data-amount="{{ $expense->amount }}"
                                    data-paid-to="{{ $expense->paid_to }}"
                                    data-description="{{ $expense->description }}"
                                    data-prepared-by="{{ $expense->prepared_by }}"
                                >
                                    Edit
                                </button>
                                <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this expense?')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No expenses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
            <div class="card-footer">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');

            document.getElementById('update_expense_category_id').value = this.getAttribute('data-category-id');
            document.getElementById('update_expense_date').value = this.getAttribute('data-date');
            document.getElementById('update_amount').value = this.getAttribute('data-amount');
            document.getElementById('update_paid_to').value = this.getAttribute('data-paid-to') || '';
            document.getElementById('update_description').value = this.getAttribute('data-description') || '';
            document.getElementById('update_prepared_by').value = this.getAttribute('data-prepared-by') || '';

            document.getElementById('updateExpenseForm').action = `/admin/expenses/${id}`;
        });
    });
</script>
@endsection
