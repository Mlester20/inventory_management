<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * The Customer Type list (add / edit / delete), modelled on Categories. A type is stored on
 * customers by name, so renaming one renames it on its customers too, and a type still used
 * by a customer can't be deleted. "Walk-In" is built in and stays as it is.
 */
class CustomerTypeController extends Controller
{
    public function index()
    {
        $types = CustomerType::orderBy('name')->get()->each(fn (CustomerType $type) => $type->customers_count = $type->customersCount());

        return view('admin.customer-types', compact('types'));
    }

    public function store(Request $request)
    {
        if ($this->denied()) {
            return redirect()->route('customer-types.index');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_types', 'name')],
        ]);

        // Any spelling of walk-in is the one built-in type, which already exists.
        if (Customer::isWalkInType($request->name)) {
            Alert::error('Already exists', 'Walk-In is a built-in customer type and is already in the list.');
            return redirect()->route('customer-types.index');
        }

        $type = CustomerType::create(['name' => trim($request->name)]);

        ActivityLog::record(
            module: 'CustomerType',
            action: 'created',
            loggable: $type,
            description: "Created customer type {$type->name}",
        );

        Alert::success('Success', 'Customer type created successfully');
        return redirect()->route('customer-types.index');
    }

    public function update(Request $request, CustomerType $customerType)
    {
        if ($this->denied()) {
            return redirect()->route('customer-types.index');
        }

        if ($customerType->isProtected()) {
            Alert::error('Cannot edit', 'Walk-In is a built-in customer type (personal use, no withholding tax) and cannot be renamed.');
            return redirect()->route('customer-types.index');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_types', 'name')->ignore($customerType->id)],
        ]);

        // Naming a type "Walk-In" would make it behave as one (no withholding) — that has to be the built-in one.
        if (Customer::isWalkInType($request->name)) {
            Alert::error('Cannot use this name', 'Walk-In is a built-in customer type; pick another name.');
            return redirect()->route('customer-types.index');
        }

        $old = $customerType->name;
        $new = trim($request->name);

        // Customers carry the type by name, so they follow the rename.
        $renamed = Customer::where('customer_type', $old)->update(['customer_type' => $new]);
        $customerType->update(['name' => $new]);

        ActivityLog::record(
            module: 'CustomerType',
            action: 'updated',
            loggable: $customerType,
            description: "Renamed customer type {$old} to {$new} ({$renamed} customer(s) updated)",
            metadata: ['before' => ['name' => $old], 'after' => ['name' => $new]],
        );

        Alert::success('Success', 'Customer type updated successfully');
        return redirect()->route('customer-types.index');
    }

    public function destroy(CustomerType $customerType)
    {
        if ($this->denied()) {
            return redirect()->route('customer-types.index');
        }

        if ($customerType->isProtected()) {
            Alert::error('Cannot delete', 'Walk-In is a built-in customer type and cannot be deleted.');
            return redirect()->route('customer-types.index');
        }

        if ($customerType->customersCount() > 0) {
            Alert::error('Cannot delete', 'This customer type is still assigned to customers and cannot be deleted.');
            return redirect()->route('customer-types.index');
        }

        $name = $customerType->name;
        $customerType->delete();

        ActivityLog::record(
            module: 'CustomerType',
            action: 'deleted',
            loggable: $customerType,
            description: "Deleted customer type {$name}",
        );

        Alert::success('Success', 'Customer type deleted successfully');
        return redirect()->route('customer-types.index');
    }

    protected function denied(): bool
    {
        if (auth()->user()->role === 'admin') {
            return false;
        }

        Alert::error('Not allowed', 'Managing customer types is restricted to full admin accounts.');

        return true;
    }
}
