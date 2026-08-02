<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $stockTransferService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $stockTransfers = StockTransfer::query()
            ->with('fromLocation', 'toLocation', 'preparedBy')
            ->when($search, function ($query, $search) {
                $query->where('reference', 'like', "%{$search}%");
            })
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock-transfers.index', compact('stockTransfers', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $locations = Location::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();

        $genericNamesForJs = $genericNames->map(fn (GenericName $g) => [
            'id' => $g->id,
            'generic_name' => $g->generic_name,
            'unit' => $g->unit,
            'category_name' => $g->category->category_name,
        ])->values();

        return view('admin.stock-transfers.create', compact('locations', 'users', 'genericNamesForJs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.product_batch_id' => 'required|exists:product_batches,id',
            'lines.*.qty' => 'required|integer|min:1',
        ]);

        try {
            $stockTransfer = $this->stockTransferService->createStockTransfer($validated, auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'StockTransfer',
            action: 'created',
            loggable: $stockTransfer,
            description: "Created Stock Transfer {$stockTransfer->reference} ({$stockTransfer->fromLocation->name} → {$stockTransfer->toLocation->name})",
        );

        Alert::success('Success', 'Stock Transfer created successfully');
        return redirect()->route('stock-transfers.show', $stockTransfer);
    }

    /**
     * Display the specified resource.
     */
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load('fromLocation', 'toLocation', 'preparedBy', 'lines.productBatch.product');

        return view('admin.stock-transfers.show', compact('stockTransfer'));
    }
}
