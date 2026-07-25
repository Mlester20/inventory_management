<?php

namespace App\Http\Controllers;

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

        $salesQuotes = SalesQuote::query()
            ->with('customer')
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

        return view('admin.sales-quotes.index', compact('salesQuotes', 'search'));
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

        return view('admin.sales-quotes.create', compact('customers', 'genericNames', 'users', 'genericNamesForJs'));
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
        if ($salesQuote->status === 'converted') {
            Alert::error('Cannot delete', 'This Sales Quote has already been converted to a Sales Order and cannot be deleted.');
            return redirect()->route('sales-quotes.index');
        }

        $salesQuote->delete();

        Alert::success('Success', 'Sales Quote deleted successfully');
        return redirect()->route('sales-quotes.index');
    }
}
