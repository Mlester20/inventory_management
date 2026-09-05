<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\SalesQuote;
use App\Models\User;
use App\Services\SalesQuoteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class SalesQuoteController extends Controller
{
    public function __construct(protected SalesQuoteService $salesQuoteService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $showArchived = $request->boolean('show_archived');

        $salesQuotes = SalesQuote::query()
            ->with('customer')
            ->when($showArchived, fn ($q) => $q->whereNotNull('archived_at'))
            ->when(! $showArchived, fn ($q) => $q->whereNull('archived_at'))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('quote_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-quotes.index', compact('salesQuotes', 'search', 'showArchived'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
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

        // A failed validation redirect flashes the submitted `items` array
        // via old() — reuse it so the JS-built line-item rows repopulate
        // from what the user actually typed, instead of resetting to blank.
        $prefillLines = [];
        if (old('items')) {
            $prefillLines = collect(old('items'))
                ->map(function ($line) use ($genericNames) {
                    $genericName = $genericNames->firstWhere('id', $line['generic_name_id'] ?? null);

                    return [
                        'generic_label' => $genericName
                            ? "{$genericName->generic_name} ({$genericName->unit}) — {$genericName->category->category_name}"
                            : null,
                        'generic_name_id' => $line['generic_name_id'] ?? null,
                        'qty' => $line['qty'] ?? null,
                        'price' => $line['price'] ?? null,
                    ];
                })
                ->values();
        }

        return view('admin.sales-quotes.create', compact('customers', 'genericNames', 'users', 'genericNamesForJs', 'prefillLines'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'quote_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.generic_name_id' => 'required|exists:generic_names,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $salesQuote = $this->salesQuoteService->createSalesQuote($validated);

        ActivityLog::record(
            module: 'SalesQuote',
            action: 'created',
            loggable: $salesQuote,
            description: "Created Sales Quote {$salesQuote->quote_no}",
        );

        Alert::success('Success', 'Sales Quote created successfully');
        return redirect()->route('sales-quotes.show', $salesQuote);
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesQuote $salesQuote)
    {
        $salesQuote->load('customer', 'preparedBy', 'items.genericName', 'salesOrder');
        $users = User::orderBy('name')->get();

        return view('admin.sales-quotes.show', compact('salesQuote', 'users'));
    }

    /**
     * Convert this Sales Quote into a Sales Order, copying its lines.
     */
    public function convertToSalesOrder(Request $request, SalesQuote $salesQuote)
    {
        $validated = $request->validate([
            'order_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
        ]);

        try {
            $salesOrder = $this->salesQuoteService->convertToSalesOrder($salesQuote, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        ActivityLog::record(
            module: 'SalesQuote',
            action: 'converted_to_sales_order',
            loggable: $salesQuote,
            description: "Converted Sales Quote {$salesQuote->quote_no} to Sales Order {$salesOrder->so_no}",
            metadata: ['sales_order_id' => $salesOrder->id],
        );

        Alert::success('Success', 'Sales Quote converted to Sales Order successfully');
        return redirect()->route('sales-orders.show', $salesOrder);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesQuote $salesQuote)
    {
        Alert::info('Not supported', 'Editing an issued Sales Quote is not supported.');
        return redirect()->route('sales-quotes.show', $salesQuote);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesQuote $salesQuote)
    {
        Alert::info('Not supported', 'Editing an issued Sales Quote is not supported.');
        return redirect()->route('sales-quotes.show', $salesQuote);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesQuote $salesQuote)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Deleting Sales Quotes is restricted to full admin accounts.');
            return redirect()->route('sales-quotes.index');
        }

        if ($salesQuote->status === 'converted') {
            Alert::error('Cannot delete', 'This Sales Quote has already been converted to a Sales Order and cannot be deleted.');
            return redirect()->route('sales-quotes.index');
        }

        $quoteNo = $salesQuote->quote_no;
        $salesQuote->delete();

        ActivityLog::record(
            module: 'SalesQuote',
            action: 'deleted',
            loggable: $salesQuote,
            description: "Deleted Sales Quote {$quoteNo}",
        );

        Alert::success('Success', 'Sales Quote deleted successfully');
        return redirect()->route('sales-quotes.index');
    }

    /**
     * Archive a Sales Quote — purely a listing-declutter flag, no bearing on
     * status/history. Manual per-record action, reversible via unarchive().
     */
    public function archive(SalesQuote $salesQuote)
    {
        $salesQuote->update(['archived_at' => now()]);

        ActivityLog::record(
            module: 'SalesQuote',
            action: 'archived',
            loggable: $salesQuote,
            description: "Archived Sales Quote {$salesQuote->quote_no}",
        );

        Alert::success('Success', 'Sales Quote archived.');
        return redirect()->route('sales-quotes.index');
    }

    public function unarchive(SalesQuote $salesQuote)
    {
        $salesQuote->update(['archived_at' => null]);

        ActivityLog::record(
            module: 'SalesQuote',
            action: 'unarchived',
            loggable: $salesQuote,
            description: "Unarchived Sales Quote {$salesQuote->quote_no}",
        );

        Alert::success('Success', 'Sales Quote unarchived.');
        return redirect()->route('sales-quotes.index', ['show_archived' => 1]);
    }
}
