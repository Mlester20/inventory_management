<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Repack;
use App\Models\User;
use App\Services\RepackService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Repack: converts stock of one product into stock of another at the same
 * location (e.g. breaking a BX into loose PC). See
 * docs/repacking-unit-conversion-plan.md. v1 posts immediately — no draft
 * status, matching the Stock Transfer pattern's simpler half (no
 * saveDraft/finalizeDraft/edit/update/destroy yet).
 */
class RepackController extends Controller
{
    public function __construct(protected RepackService $repackService) {}

    public function index(Request $request)
    {
        $search = $request->input('search');

        $repacks = Repack::query()
            ->with('location', 'preparedBy')
            ->when($search, fn ($query) => $query->where('reference', 'like', "%{$search}%"))
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.repacks.index', compact('repacks', 'search'));
    }

    public function create()
    {
        return view('admin.repacks.create', $this->formData());
    }

    protected function formData(): array
    {
        $locations = Location::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $genericNames = GenericName::with(['category', 'products'])->orderBy('generic_name')->get();

        $genericNamesForJs = $genericNames->map(fn (GenericName $g) => [
            'id' => $g->id,
            'generic_name' => $g->generic_name,
            'unit' => $g->unit,
            'category_name' => $g->category->category_name,
            // Destination product picker doesn't check stock (repacking adds
            // NEW stock there) — unlike the source side, which fetches
            // in-stock batches from /api/generic-names/{id}/available-items.
            'products' => $g->products->map(fn ($p) => [
                'id' => $p->id,
                'label' => $p->description ?: ($p->brand_name ?: $p->item_name),
            ])->values(),
        ])->values();

        return compact('locations', 'users', 'genericNamesForJs');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'location_id' => 'required|exists:locations,id',
            'prepared_by' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.source_batch_id' => 'required|exists:product_batches,id',
            'lines.*.source_qty' => 'required|integer|min:1',
            'lines.*.destination_product_id' => 'required|exists:products,id',
            'lines.*.destination_qty' => 'required|integer|min:1',
            'lines.*.destination_batch_no' => 'nullable|string|max:100',
            'lines.*.destination_expiration_date' => 'nullable|date',
        ]);

        try {
            $repack = $this->repackService->createRepack($validated, auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'Repack',
            action: 'created',
            loggable: $repack,
            description: "Created Repack {$repack->reference} at {$repack->location->name}",
        );

        Alert::success('Success', 'Repack created successfully');
        return redirect()->route('repacks.show', $repack);
    }

    public function show(Repack $repack)
    {
        $repack->load('location', 'preparedBy', 'voidedBy', 'lines.sourceBatch.product', 'lines.destinationProduct', 'lines.destinationBatch');

        return view('admin.repacks.show', compact('repack'));
    }

    /**
     * Void a Repack while every piece it produced is still there — see
     * RepackService::voidRepack(). Restricted to full admin accounts, same as
     * cancelling an Invoice.
     */
    public function void(Request $request, Repack $repack)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Voiding a Repack is restricted to full admin accounts.');
            return redirect()->route('repacks.show', $repack);
        }

        $validated = $request->validate([
            'void_reason' => 'nullable|string|max:500',
        ]);

        try {
            $repack = $this->repackService->voidRepack($repack, auth()->id(), $validated['void_reason'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->route('repacks.show', $repack)->withErrors($e->errors());
        }

        ActivityLog::record(
            module: 'Repack',
            action: 'voided',
            loggable: $repack,
            description: "Voided Repack {$repack->reference}" . ($repack->void_reason ? " ({$repack->void_reason})" : ''),
        );

        Alert::success('Repack voided', 'The pieces were taken back out and the source stock was returned.');
        return redirect()->route('repacks.show', $repack);
    }
}
