<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ExpenseCategoryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:expense_categories,name',
        ]);

        ExpenseCategory::create([
            'name' => $request->name,
        ]);

        Alert::success('Success', 'Expense category created successfully');
        return redirect()->route('expenses.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        try {
            $expenseCategory->delete();
        } catch (QueryException $e) {
            Alert::error('Cannot delete', 'This category still has expenses recorded against it and cannot be deleted.');
            return redirect()->route('expenses.index');
        }

        Alert::success('Success', 'Expense category deleted successfully');
        return redirect()->route('expenses.index');
    }
}
