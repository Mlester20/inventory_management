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

    protected function formData(?Repack $editing = null): array
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
                // Own generic's unit — the destination picker aggregates products across every
                // generic that shares a generic_name TEXT (a BX and its PC are sometimes two
                // separate generic_names rows differing only by Unit), so each option needs its
                // own unit tagged on to stay distinguishable once merged into one list.
                'unit' => $g->unit,
                // For the suggested-price box: what this item currently sells/costs, per Sir's
                // "divide the box's Price/Cost evenly across the pieces" rule.
                'unit_price' => (float) $p->unit_price,
                'unit_cost' => (float) $p->unit_cost,
            ])->values(),
        ])->values();

        $prefillLines = [];
        if ($editing) {
            $editing->load('lines.sourceBatch.product.genericName');

            $prefillLines = $editing->lines->map(fn ($line) => [
                'generic_label' => $line->sourceBatch->product->genericName
                    ? "{$line->sourceBatch->product->genericName->generic_name} ({$line->sourceBatch->product->genericName->unit}) — {$line->sourceBatch->product->genericName->category->category_name}"
                    : null,
                'source_batch_id' => $line->source_batch_id,
                'source_qty' => $line->source_qty,
                'destination_product_id' => $line->destination_product_id,
                'destination_qty' => $line->destination_qty,
                'destination_batch_no' => $line->destinationBatch?->batch_no,
                'destination_expiration_date' => $line->destinationBatch?->expiration_date?->toDateString(),
                'destination_price' => $line->destination_price !== null ? (float) $line->destination_price : null,
                'destination_cost' => $line->destination_cost !== null ? (float) $line->destination_cost : null,
                'apply_price' => $line->price_applied,
            ])->values();
        }

        return compact('locations', 'users', 'genericNamesForJs', 'prefillLines') + ['editingRepack' => $editing];
    }

    protected function draftValidationRules(): array
    {
        return [
            'date' => 'nullable|date',
            'location_id' => 'nullable|exists:locations,id',
            'prepared_by' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.source_batch_id' => 'nullable|exists:product_batches,id',
            'lines.*.source_qty' => 'nullable|integer|min:0',
            'lines.*.destination_product_id' => 'nullable|exists:products,id',
            'lines.*.destination_qty' => 'nullable|integer|min:0',
            'lines.*.destination_batch_no' => 'nullable|string|max:100',
            'lines.*.destination_expiration_date' => 'nullable|date',
            'lines.*.destination_price' => 'nullable|numeric|min:0',
            'lines.*.destination_cost' => 'nullable|numeric|min:0',
            'lines.*.apply_price' => 'nullable|boolean',
        ];
    }

    protected function postedValidationRules(): array
    {
        return [
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
            'lines.*.destination_price' => 'nullable|numeric|min:0',
            'lines.*.destination_cost' => 'nullable|numeric|min:0',
            'lines.*.apply_price' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());
            $repack = $this->repackService->saveDraft($validated, auth()->id());

            ActivityLog::record(
                module: 'Repack',
                action: 'draft_saved',
                loggable: $repack,
                description: "Saved draft Repack {$repack->reference}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the Repacks list before posting.');
            return redirect()->route('repacks.show', $repack);
        }

        $validated = $request->validate($this->postedValidationRules());

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
     * Only a draft can be edited — a posted Repack already moved stock and
     * (maybe) applied a Price/Cost, so editing that isn't supported; void it
     * instead, same as Invoice/Goods Receipt/Delivery Receipt handle it.
     */
    public function edit(Repack $repack)
    {
        if (! $repack->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Repack is not supported.');
            return redirect()->route('repacks.show', $repack);
        }

        return view('admin.repacks.create', $this->formData($repack));
    }

    public function update(Request $request, Repack $repack)
    {
        if (! $repack->isDraft()) {
            Alert::info('Not supported', 'Editing a posted Repack is not supported.');
            return redirect()->route('repacks.show', $repack);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());
            $repack = $this->repackService->saveDraft($validated, auth()->id(), $repack);

            ActivityLog::record(
                module: 'Repack',
                action: 'draft_updated',
                loggable: $repack,
                description: "Updated draft Repack {$repack->reference}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the Repacks list before posting.');
            return redirect()->route('repacks.show', $repack);
        }

        $validated = $request->validate($this->postedValidationRules());

        try {
            $repack = $this->repackService->createRepack($validated, auth()->id(), $repack);
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
