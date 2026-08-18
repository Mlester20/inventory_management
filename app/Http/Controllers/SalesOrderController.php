<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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
            ->paginate(10)
            ->withQueryString();

        return view('admin.sales-orders.index', compact('salesOrders', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.sales-orders.create', $this->formData());
    }

    /**
     * Data shared by the create and edit-draft forms. When editing a draft,
     * its own items are mapped back into the shape the item picker's JS
     * expects to prefill (generic_label so it can be typed straight into
     * the search input — everything is already client-side, no fetch
     * needed, same as Purchase Order's picker).
     */
    protected function formData(?SalesOrder $editing = null): array
    {
        $customers = Customer::orderBy('customer_name')->get();
        $genericNames = GenericName::with(['category', 'products' => function ($query) {
            $query->withSum('locationStocks', 'qty');
        }])->orderBy('generic_name')->get();
        $users = User::orderBy('name')->get();

        $genericNamesForJs = $genericNames->map(function ($genericName) {
            // Pricing is suggested from whichever brand under this generic
            // currently has stock anywhere (first in-stock product); the
            // cashier can still override the price per line.
            $firstProduct = $genericName->products->first(fn (Product $product) => ($product->location_stocks_sum_qty ?? 0) > 0)
                ?? $genericName->products->first();

            return [
                'id' => $genericName->id,
                'code' => $genericName->code,
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

        $prefillLines = [];

        if ($editing) {
            $editing->load('items.genericName.category');

            $prefillLines = $editing->items
                ->filter(fn ($line) => $line->genericName)
                ->map(fn ($line) => [
                    'generic_label' => "{$line->genericName->generic_name} ({$line->genericName->unit}) — {$line->genericName->category->category_name}",
                    'generic_name_id' => $line->generic_name_id,
                    'qty' => $line->qty,
                    'price' => $line->price !== null ? (float) $line->price : null,
                    'advance_order_qty' => $line->advance_order_qty,
                ])
                ->values();
        }

        return compact('customers', 'genericNames', 'users', 'genericNamesForJs', 'prefillLines') + ['editingSalesOrder' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $salesOrder = $this->salesOrderService->saveDraft($validated);

            ActivityLog::record(
                module: 'SalesOrder',
                action: 'draft_saved',
                loggable: $salesOrder,
                description: "Saved draft Sales Order {$salesOrder->so_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('sales-orders.show', $salesOrder);
        }

        $validated = $request->validate($this->postedValidationRules());

        $salesOrder = $this->salesOrderService->createSalesOrder($validated);

        ActivityLog::record(
            module: 'SalesOrder',
            action: 'created',
            loggable: $salesOrder,
            description: "Created Sales Order {$salesOrder->so_no}",
        );

        Alert::success('Success', 'Sales Order created successfully');
        return redirect()->route('sales-orders.show', $salesOrder);
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'po_no' => 'nullable|string|max:255',
            'order_date' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'nullable|array',
            'items.*.generic_name_id' => 'nullable|exists:generic_names,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.advance_order_qty' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Strict rules for the moment the order is actually issued — whether
     * that's a direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'po_no' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.generic_name_id' => 'required|exists:generic_names,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.advance_order_qty' => 'nullable|integer|min:0',
        ];
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
     * A draft has never been delivered against, so editing it is completely
     * safe — reuses the same create view, pre-filled with the draft's own
     * current items. A posted (issued) order is not supported for editing,
     * matching the existing behavior for non-draft orders.
     */
    public function edit(SalesOrder $salesOrder)
    {
        if (! $salesOrder->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Sales Order is not supported.');
            return redirect()->route('sales-orders.show', $salesOrder);
        }

        return view('admin.sales-orders.create', $this->formData($salesOrder));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesOrder $salesOrder)
    {
        if (! $salesOrder->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Sales Order is not supported.');
            return redirect()->route('sales-orders.show', $salesOrder);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $salesOrder = $this->salesOrderService->saveDraft($validated, $salesOrder);

            ActivityLog::record(
                module: 'SalesOrder',
                action: 'draft_updated',
                loggable: $salesOrder,
                description: "Updated draft Sales Order {$salesOrder->so_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('sales-orders.show', $salesOrder);
        }

        $validated = $request->validate($this->postedValidationRules());

        $salesOrder = $this->salesOrderService->finalizeDraft($salesOrder, $validated);

        ActivityLog::record(
            module: 'SalesOrder',
            action: 'finalized',
            loggable: $salesOrder,
            description: "Finalized Sales Order {$salesOrder->so_no}",
        );

        Alert::success('Success', 'Sales Order issued successfully');
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

        $soNo = $salesOrder->so_no;
        $salesOrder->delete();

        ActivityLog::record(
            module: 'SalesOrder',
            action: 'deleted',
            loggable: $salesOrder,
            description: "Deleted Sales Order {$soNo}",
        );

        Alert::success('Success', 'Sales Order deleted successfully');
        return redirect()->route('sales-orders.index');
    }
}
