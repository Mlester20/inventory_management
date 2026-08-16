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
        return view('admin.stock-transfers.create', $this->formData());
    }

    /**
     * Data shared by the create and edit-draft forms: location/user/generic
     * name pickers, plus — when editing a draft — that draft's own lines
     * pre-mapped into the shape the "Generic Description" picker's async
     * item-loading JS expects (generic_label so it can be typed straight
     * into the search input, product_batch_id/qty to preselect once the
     * matching items are fetched).
     */
    protected function formData(?StockTransfer $editing = null): array
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

        $prefillLines = [];

        if ($editing) {
            $editing->load('lines.productBatch.product.genericName.category');

            $prefillLines = $editing->lines
                ->filter(fn ($line) => $line->productBatch?->product?->genericName)
                ->map(function ($line) {
                    $generic = $line->productBatch->product->genericName;

                    return [
                        'generic_label' => "{$generic->generic_name} ({$generic->unit}) — {$generic->category->category_name}",
                        'product_batch_id' => $line->product_batch_id,
                        'qty' => $line->qty,
                    ];
                })
                ->values();
        }

        return compact('locations', 'users', 'genericNamesForJs', 'prefillLines') + ['editingStockTransfer' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $stockTransfer = $this->stockTransferService->saveDraft($validated, auth()->id());

            ActivityLog::record(
                module: 'StockTransfer',
                action: 'draft_saved',
                loggable: $stockTransfer,
                description: "Saved draft Stock Transfer {$stockTransfer->reference}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('stock-transfers.show', $stockTransfer);
        }

        $validated = $request->validate($this->postedValidationRules());

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
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'date' => 'nullable|date',
            'from_location_id' => 'nullable|exists:locations,id',
            'to_location_id' => 'nullable|exists:locations,id',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'nullable|array',
            'lines.*.product_batch_id' => 'nullable|exists:product_batches,id',
            'lines.*.qty' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Strict rules for the moment stock actually moves — whether that's a
     * direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.product_batch_id' => 'required|exists:product_batches,id',
            'lines.*.qty' => 'required|integer|min:1',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load('fromLocation', 'toLocation', 'preparedBy', 'lines.productBatch.product');

        return view('admin.stock-transfers.show', compact('stockTransfer'));
    }

    /**
     * A draft has never touched stock, so editing it is completely safe —
     * reuses the same create view, pre-filled with the draft's own current
     * lines. A posted transfer already mutated location_stocks and wrote a
     * stock ledger entry the moment it was created, so editing that is not
     * supported.
     */
    public function edit(StockTransfer $stockTransfer)
    {
        if (! $stockTransfer->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Stock Transfer is not supported.');
            return redirect()->route('stock-transfers.show', $stockTransfer);
        }

        return view('admin.stock-transfers.create', $this->formData($stockTransfer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockTransfer $stockTransfer)
    {
        if (! $stockTransfer->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Stock Transfer is not supported.');
            return redirect()->route('stock-transfers.show', $stockTransfer);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $stockTransfer = $this->stockTransferService->saveDraft($validated, auth()->id(), $stockTransfer);

            ActivityLog::record(
                module: 'StockTransfer',
                action: 'draft_updated',
                loggable: $stockTransfer,
                description: "Updated draft Stock Transfer {$stockTransfer->reference}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('stock-transfers.show', $stockTransfer);
        }

        $validated = $request->validate($this->postedValidationRules());

        try {
            $stockTransfer = $this->stockTransferService->finalizeDraft($stockTransfer, $validated, auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'StockTransfer',
            action: 'finalized',
            loggable: $stockTransfer,
            description: "Finalized Stock Transfer {$stockTransfer->reference} ({$stockTransfer->fromLocation->name} → {$stockTransfer->toLocation->name})",
        );

        Alert::success('Success', 'Stock Transfer posted successfully');
        return redirect()->route('stock-transfers.show', $stockTransfer);
    }

    /**
     * A draft never touched stock, so deleting it outright is safe. A
     * posted transfer can never be deleted — same policy as everywhere else.
     */
    public function destroy(StockTransfer $stockTransfer)
    {
        if (! $stockTransfer->isDraft()) {
            Alert::info('Not supported', 'Deleting a posted Stock Transfer is not supported.');
            return redirect()->route('stock-transfers.show', $stockTransfer);
        }

        $reference = $stockTransfer->reference;
        $stockTransfer->delete();

        ActivityLog::record(
            module: 'StockTransfer',
            action: 'draft_deleted',
            description: "Deleted draft Stock Transfer {$reference}",
        );

        Alert::success('Deleted', "Draft {$reference} has been deleted.");
        return redirect()->route('stock-transfers.index');
    }
}
