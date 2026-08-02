<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Taxes;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class TaxesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $taxes = Taxes::orderBy('name')->paginate(15);
        return view('admin.taxes', compact('taxes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tax_name' => 'required|string|unique:taxes,name',
            'rate' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $tax = Taxes::create([
            'name' => $request->tax_name,
            'rate' => $request->rate,
            'is_active' => $request->is_active
        ]);

        ActivityLog::record(
            module: 'Taxes',
            action: 'created',
            loggable: $tax,
            description: "Created tax {$tax->name} ({$tax->rate}%)",
        );

        Alert::success('Success', 'Tax created successfully');
        return redirect()->route('taxes.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Taxes $tax)
    {
        $request->validate([
            'tax_name' => 'required|string|unique:taxes,name,' . $tax->id,
            'rate' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $original = $tax->getOriginal();
        $tax->update([
            'name' => $request->tax_name,
            'rate' => $request->rate,
            'is_active' => $request->is_active,
        ]);

        $changes = $tax->getChanges();
        ActivityLog::record(
            module: 'Taxes',
            action: 'updated',
            loggable: $tax,
            description: "Updated tax {$tax->name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        Alert::success('Success', 'Tax updated successfully');
        return redirect()->route('taxes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Taxes $tax)
    {
        $taxName = $tax->name;
        $tax->delete();

        ActivityLog::record(
            module: 'Taxes',
            action: 'deleted',
            loggable: $tax,
            description: "Deleted tax {$taxName}",
        );

        Alert::success('success', 'Tax Deleted Successfully!');
        return redirect()->route('taxes.index');
    }
}