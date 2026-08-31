<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\GenericName;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class GenericNameController extends Controller
{
    /**
     * Display a listing of the resource (legacy Categories admin page —
     * Category CRUD only; Generic Item CRUD now lives on the Inventory
     * Items module's "General Item" tab).
     */
    public function index()
    {
        $categories = Category::orderBy('category_name')->get();

        return view('admin.categories', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:generic_names,code',
            'generic_name' => 'required|unique:generic_names,generic_name',
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
            'vat_type' => 'required|in:' . implode(',', array_keys(GenericName::VAT_TYPES)),
        ]);

        $genericName = GenericName::create([
            'code' => $request->code,
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
            'vat_type' => $request->vat_type,
        ]);

        ActivityLog::record(
            module: 'GenericName',
            action: 'created',
            loggable: $genericName,
            description: "Created generic item {$genericName->generic_name}",
        );

        Alert::success('Success', 'Generic item created successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GenericName $genericName)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:generic_names,code,' . $genericName->id,
            'generic_name' => 'required|unique:generic_names,generic_name,' . $genericName->id,
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
            'vat_type' => 'required|in:' . implode(',', array_keys(GenericName::VAT_TYPES)),
        ]);

        $original = $genericName->getOriginal();
        $genericName->update([
            'code' => $request->code,
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
            'vat_type' => $request->vat_type,
        ]);

        $changes = $genericName->getChanges();
        ActivityLog::record(
            module: 'GenericName',
            action: 'updated',
            loggable: $genericName,
            description: "Updated generic item {$genericName->generic_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        Alert::success('Success', 'Generic item updated successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GenericName $genericName)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Deleting items is restricted to full admin accounts.');
            return redirect()->route('inventory-items.index', ['tab' => 'general']);
        }

        $genericNameLabel = $genericName->generic_name;
        $snapshot = $genericName->getAttributes();

        try {
            $genericName->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                Alert::error('Cannot delete', "{$genericNameLabel} still has related records (sales orders, sales quotes, or products) and cannot be deleted.");
                return redirect()->route('inventory-items.index', ['tab' => 'general']);
            }
            throw $e;
        }

        ActivityLog::record(
            module: 'GenericName',
            action: 'deleted',
            loggable: $genericName,
            description: "Deleted generic item {$genericNameLabel}",
            metadata: ['deleted_record' => $snapshot],
        );

        Alert::success('Success', 'Generic item deleted successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    /**
     * Restore a soft-deleted generic item from the trash.
     */
    public function restore(int $id)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Restoring items is restricted to full admin accounts.');
            return redirect()->route('inventory-items.index', ['tab' => 'general']);
        }

        $genericName = GenericName::onlyTrashed()->findOrFail($id);
        $genericName->restore();

        ActivityLog::record(
            module: 'GenericName',
            action: 'restored',
            loggable: $genericName,
            description: "Restored generic item {$genericName->generic_name}",
        );

        Alert::success('Success', 'Generic item restored successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general', 'show_trashed' => 1]);
    }

    public function archive(GenericName $genericName)
    {
        $genericName->update(['archived_at' => now()]);

        ActivityLog::record(
            module: 'GenericName',
            action: 'archived',
            loggable: $genericName,
            description: "Archived generic item {$genericName->generic_name}",
        );

        Alert::success('Success', 'Generic item archived.');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    public function unarchive(GenericName $genericName)
    {
        $genericName->update(['archived_at' => null]);

        ActivityLog::record(
            module: 'GenericName',
            action: 'unarchived',
            loggable: $genericName,
            description: "Unarchived generic item {$genericName->generic_name}",
        );

        Alert::success('Success', 'Generic item unarchived.');
        return redirect()->route('inventory-items.index', ['tab' => 'general', 'show_archived' => 1]);
    }
}
