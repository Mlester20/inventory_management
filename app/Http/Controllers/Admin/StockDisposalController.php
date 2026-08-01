<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockDisposal;
use App\Models\User;
use App\Services\StockDisposalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class StockDisposalController extends Controller
{
    public function __construct(protected StockDisposalService $stockDisposalService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $stockDisposals = StockDisposal::query()
            ->withCount('lines')
            ->with('preparedBy')
            ->when($search, function ($query, $search) {
                $query->where('reference', 'like', "%{$search}%");
            })
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-disposals.index', compact('stockDisposals', 'search'));
    }

    /**
     * Show the form for creating a new resource. Reachable standalone, or
     * pre-filled from the Product Expiration Report's "Dispose" action via
     * ?product_batch_id=.
     */
    public function create(Request $request)
    {
        $users = User::orderBy('name')->get();

        $products = Product::with(['genericName', 'batches.locationStocks.location'])
            ->orderBy('item_name')
            ->get();

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->item_name,
            'batches' => $p->batches->map(fn (ProductBatch $b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
                'locations' => $b->locationStocks
                    ->filter(fn ($ls) => $ls->qty > 0)
                    ->map(fn ($ls) => [
                        'location_id' => $ls->location_id,
                        'location_name' => $ls->location->name,
                        'qty' => $ls->qty,
                    ])->values(),
            ])->values(),
        ])->values();

        $preselectedBatchId = $request->query('product_batch_id');

        return view('admin.stock-disposals.create', compact('users', 'productsForJs', 'preselectedBatchId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reason' => 'required|in:' . implode(',', array_keys(StockDisposal::REASONS)),
            'remarks' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.product_batch_id' => 'required|exists:product_batches,id',
            'lines.*.location_id' => 'required|exists:locations,id',
            'lines.*.qty' => 'required|integer|min:1',
        ]);

        try {
            $stockDisposal = $this->stockDisposalService->createStockDisposal($validated, auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        Alert::success('Success', 'Stock Disposal recorded successfully');
        return redirect()->route('stock-disposals.show', $stockDisposal);
    }

    /**
     * Display the specified resource.
     */
    public function show(StockDisposal $stockDisposal)
    {
        $stockDisposal->load('preparedBy', 'lines.productBatch.product.supplier', 'lines.location');

        return view('admin.stock-disposals.show', compact('stockDisposal'));
    }
}
