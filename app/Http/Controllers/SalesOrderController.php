<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class SalesOrderController extends Controller
{
    public function __construct(protected SalesOrderService $salesOrderService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $salesOrders = SalesOrder::query()
            ->with('customer')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('so_no', 'like', "%{$search}%")
                        ->orWhere('po_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-orders.index', compact('salesOrders', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::orderBy('customer_name')->get();
        $genericNames = GenericName::with(['category', 'products' => function ($query) {
            $query->withSum('batches', 'qty');
        }])->orderBy('generic_name')->get();
        $users = User::orderBy('name')->get();

        $genericNamesForJs = $genericNames->map(function ($genericName) {
            // Pricing is suggested from whichever brand under this generic
            // currently has stock (first in-stock product); the cashier can
            // still override the price per line.
            $firstProduct = $genericName->products->first(fn (Product $product) => ($product->batches_sum_qty ?? 0) > 0)
                ?? $genericName->products->first();

            return [
                'id' => $genericName->id,
                'generic_name' => $genericName->generic_name,
                'unit' => $genericName->unit,
                'category_name' => $genericName->category->category_name,
                'prices' => [
                    'retail' => $firstProduct?->unit_price,
                    'wholesale' => $firstProduct?->wholesale_price,
                    'price_level_1' => $firstProduct?->price_1,
                    'price_level_2' => $firstProduct?->price_2,
                    'price_level_3' => $firstProduct?->price_3,
                ],
            ];
        })->values();

        return view('admin.sales-orders.create', compact('customers', 'genericNames', 'users', 'genericNamesForJs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'po_no' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.generic_name_id' => 'required|exists:generic_names,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.advance_order_qty' => 'nullable|integer|min:0',
        ]);

        $salesOrder = $this->salesOrderService->createSalesOrder($validated);

        Alert::success('Success', 'Sales Order created successfully');
        return redirect()->route('sales-orders.show', $salesOrder);
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load('customer', 'preparedBy', 'items.genericName', 'deliveryReceipts');

        return view('admin.sales-orders.show', compact('salesOrder'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesOrder $salesOrder)
    {
        Alert::info('Not supported', 'Editing an issued Sales Order is not supported.');
        return redirect()->route('sales-orders.show', $salesOrder);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesOrder $salesOrder)
    {
        Alert::info('Not supported', 'Editing an issued Sales Order is not supported.');
        return redirect()->route('sales-orders.show', $salesOrder);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesOrder $salesOrder)
    {
        if ($salesOrder->items()->where('delivered_qty', '>', 0)->exists()) {
            Alert::error('Cannot delete', 'This Sales Order already has delivered items and cannot be deleted.');
            return redirect()->route('sales-orders.index');
        }

        $salesOrder->delete();

        Alert::success('Success', 'Sales Order deleted successfully');
        return redirect()->route('sales-orders.index');
    }
}
