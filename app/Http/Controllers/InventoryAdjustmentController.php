<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class InventoryAdjustmentController extends Controller
{
    public function __construct(protected InventoryAdjustmentService $inventoryAdjustmentService) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $adjustments = InventoryAdjustment::query()
            ->with('reversedBy')
            ->when($search, function ($query, $search) {
                $query->where('adjustment_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory-adjustments.index', compact('adjustments', 'search', 'status'));
    }

    public function create(Request $request)
    {
        $products = Product::with(['genericName', 'batches'])->orderBy('item_name')->get();
        $users = User::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $preselectedProductId = $request->query('product_id');

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->description ?: $p->item_name,
            'code' => $p->code,
            'unit' => $p->genericName->unit ?? '',
            'batches' => $p->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
            ])->values(),
        ])->values();

        // After a Write-off, the browser lands here with every line of the
        // original adjustment pre-filled — same product/batch/location/qty
        // as a starting point, so the user only has to correct whatever was
        // actually wrong instead of re-typing the whole thing from scratch.
        $prefillLines = [];
        $reverseOfId = $request->query('reverse_of');

        if ($reverseOfId) {
            $reverseOf = InventoryAdjustment::with('lines.product')->find($reverseOfId);

            if ($reverseOf) {
                $prefillLines = $reverseOf->lines->map(fn (InventoryAdjustmentLine $line) => [
                    'product_id' => $line->product_id,
                    'product_name' => $line->product->description ?: $line->product->item_name,
                    'batch_no' => $line->batch_no,
                    'location_id' => $line->location_id,
                    'qty' => $line->qty,
                ])->values();
            }
        }

        return view('admin.inventory-adjustments.create', compact(
            'products', 'users', 'locations', 'preselectedProductId', 'productsForJs', 'prefillLines'
        ) + ['editingAdjustment' => null]);
    }

    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $adjustment = $this->inventoryAdjustmentService->saveDraft($validated, auth()->id());

            ActivityLog::record(
                module: 'InventoryAdjustment',
                action: 'draft_saved',
                loggable: $adjustment,
                description: "Saved draft Inventory Adjustment {$adjustment->adjustment_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('inventory-adjustments.show', $adjustment);
        }

        $validated = $request->validate($this->postedValidationRules());

        $adjustment = $this->inventoryAdjustmentService->createAdjustment($validated, auth()->id());

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'created',
            loggable: $adjustment,
            description: "Created Inventory Adjustment {$adjustment->adjustment_no} (" . (InventoryAdjustment::TYPES[$adjustment->adjustment_type] ?? $adjustment->adjustment_type) . ')',
        );

        Alert::success('Success', 'Inventory Adjustment created successfully');
        return redirect()->route('inventory-adjustments.show', $adjustment);
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'adjustment_date' => 'nullable|date',
            'adjustment_type' => 'nullable|in:' . implode(',', array_keys(InventoryAdjustment::TYPES)),
            'description' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:products,id',
            'lines.*.product_batch_id' => 'nullable|exists:product_batches,id',
            'lines.*.location_id' => 'nullable|exists:locations,id',
            'lines.*.batch_no' => 'nullable|string|max:100',
            'lines.*.expiration_date' => 'nullable|date',
            'lines.*.qty' => 'nullable|integer|min:1',
            'lines.*.remarks' => 'nullable|string|max:255',
        ];
    }

    /**
     * Strict rules for the moment stock actually moves — whether that's a
     * direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'adjustment_date' => 'required|date',
            'adjustment_type' => 'required|in:' . implode(',', array_keys(InventoryAdjustment::TYPES)),
            'description' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.product_batch_id' => 'nullable|exists:product_batches,id',
            'lines.*.location_id' => 'nullable|exists:locations,id',
            'lines.*.batch_no' => 'nullable|string|max:100',
            'lines.*.expiration_date' => 'nullable|date',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.remarks' => 'nullable|string|max:255',
        ];
    }

    public function show(InventoryAdjustment $inventoryAdjustment)
    {
        $inventoryAdjustment->load(
            'preparedBy', 'lines.product', 'lines.productBatch', 'lines.location', 'reverses', 'reversedBy'
        );

        return view('admin.inventory-adjustments.show', compact('inventoryAdjustment'));
    }

    /**
     * A draft has never touched stock, so editing it is completely safe —
     * reuses the same create view, pre-filled with the draft's own current
     * lines. A posted adjustment already mutated location_stocks and wrote a
     * stock ledger entry the moment it was created (same as Goods Receipt/
     * Delivery Receipt), so editing that is still not supported.
     */
    public function edit(InventoryAdjustment $inventoryAdjustment)
    {
        if (! $inventoryAdjustment->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Inventory Adjustment is not supported. Use Write-off to reverse it, then create a new adjustment.');
            return redirect()->route('inventory-adjustments.show', $inventoryAdjustment);
        }

        $inventoryAdjustment->load('lines.product');

        $products = Product::with(['genericName', 'batches'])->orderBy('item_name')->get();
        $users = User::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $preselectedProductId = null;

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->description ?: $p->item_name,
            'code' => $p->code,
            'unit' => $p->genericName->unit ?? '',
            'batches' => $p->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
            ])->values(),
        ])->values();

        $prefillLines = $inventoryAdjustment->lines->map(fn (InventoryAdjustmentLine $line) => [
            'product_id' => $line->product_id,
            'product_name' => $line->product?->description ?: $line->product?->item_name,
            'batch_no' => $line->batch_no,
            'location_id' => $line->location_id,
            'qty' => $line->qty,
        ])->values();

        return view('admin.inventory-adjustments.create', compact(
            'products', 'users', 'locations', 'preselectedProductId', 'productsForJs', 'prefillLines'
        ) + ['editingAdjustment' => $inventoryAdjustment]);
    }

    public function update(Request $request, InventoryAdjustment $inventoryAdjustment)
    {
        if (! $inventoryAdjustment->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Inventory Adjustment is not supported. Use Write-off to reverse it, then create a new adjustment.');
            return redirect()->route('inventory-adjustments.show', $inventoryAdjustment);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $adjustment = $this->inventoryAdjustmentService->saveDraft($validated, auth()->id(), $inventoryAdjustment);

            ActivityLog::record(
                module: 'InventoryAdjustment',
                action: 'draft_updated',
                loggable: $adjustment,
                description: "Updated draft Inventory Adjustment {$adjustment->adjustment_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('inventory-adjustments.show', $adjustment);
        }

        $validated = $request->validate($this->postedValidationRules());

        $adjustment = $this->inventoryAdjustmentService->finalizeDraft($inventoryAdjustment, $validated, auth()->id());

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'finalized',
            loggable: $adjustment,
            description: "Finalized Inventory Adjustment {$adjustment->adjustment_no} (" . (InventoryAdjustment::TYPES[$adjustment->adjustment_type] ?? $adjustment->adjustment_type) . ')',
        );

        Alert::success('Success', 'Inventory Adjustment posted successfully');
        return redirect()->route('inventory-adjustments.show', $adjustment);
    }

    /**
     * A draft never touched stock, so deleting it outright is safe. A posted
     * adjustment can never be deleted — same policy as everywhere else.
     */
    public function destroy(InventoryAdjustment $inventoryAdjustment)
    {
        if (! $inventoryAdjustment->isDraft()) {
            Alert::info('Not supported', 'Deleting a posted Inventory Adjustment is not supported. Use Write-off to reverse it instead.');
            return redirect()->route('inventory-adjustments.show', $inventoryAdjustment);
        }

        $adjustmentNo = $inventoryAdjustment->adjustment_no;
        $inventoryAdjustment->delete();

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'draft_deleted',
            description: "Deleted draft Inventory Adjustment {$adjustmentNo}",
        );

        Alert::success('Deleted', "Draft {$adjustmentNo} has been deleted.");
        return redirect()->route('inventory-adjustments.index');
    }

    /**
     * Reverse a saved Inventory Adjustment (same lines, opposite direction)
     * and send the user to a pre-filled Create form for the correct entry —
     * the safe "delete and re-encode" shortcut in place of a real Delete.
     */
    public function writeOff(InventoryAdjustment $inventoryAdjustment)
    {
        try {
            $reversal = $this->inventoryAdjustmentService->writeOff($inventoryAdjustment, auth()->id());
        } catch (ValidationException $e) {
            Alert::error('Cannot write off', collect($e->errors())->flatten()->first());
            return redirect()->route('inventory-adjustments.show', $inventoryAdjustment);
        }

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'written_off',
            loggable: $reversal,
            description: "Wrote off Inventory Adjustment {$inventoryAdjustment->adjustment_no} via {$reversal->adjustment_no}",
        );

        Alert::success('Written off', "{$inventoryAdjustment->adjustment_no} has been reversed via {$reversal->adjustment_no}. Enter the correct adjustment below.");
        return redirect()->route('inventory-adjustments.create', ['reverse_of' => $inventoryAdjustment->id]);
    }
}
