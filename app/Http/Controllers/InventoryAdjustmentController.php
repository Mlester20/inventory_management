<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryAdjustment;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class InventoryAdjustmentController extends Controller
{
    public function __construct(protected InventoryAdjustmentService $inventoryAdjustmentService) {}

    public function index(Request $request)
    {
        $search = $request->input('search');

        $adjustments = InventoryAdjustment::query()
            ->when($search, function ($query, $search) {
                $query->where('adjustment_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory-adjustments.index', compact('adjustments', 'search'));
    }

    public function create(Request $request)
    {
        $products = Product::with(['genericName', 'batches'])->orderBy('item_name')->get();
        $users = User::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $preselectedProductId = $request->query('product_id');

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->item_name,
            'unit' => $p->genericName->unit ?? '',
            'batches' => $p->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
            ])->values(),
        ])->values();

        return view('admin.inventory-adjustments.create', compact('products', 'users', 'locations', 'preselectedProductId', 'productsForJs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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
        ]);

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

    public function show(InventoryAdjustment $inventoryAdjustment)
    {
        $inventoryAdjustment->load('preparedBy', 'lines.product', 'lines.productBatch', 'lines.location');

        return view('admin.inventory-adjustments.show', compact('inventoryAdjustment'));
    }
}
