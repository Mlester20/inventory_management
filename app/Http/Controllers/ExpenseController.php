<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $expenses = Expense::with('category', 'preparedBy')->latest('expense_date')->paginate(15);
        $expenseCategories = ExpenseCategory::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('admin.expenses', compact('expenses', 'expenseCategories', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'paid_to' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
        ]);

        Expense::create($validated);

        Alert::success('Success', 'Expense recorded successfully');
        return redirect()->route('expenses.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'paid_to' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
        ]);

        $expense->update($validated);

        Alert::success('Success', 'Expense updated successfully');
        return redirect()->route('expenses.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        Alert::success('Success', 'Expense deleted successfully');
        return redirect()->route('expenses.index');
    }
}
