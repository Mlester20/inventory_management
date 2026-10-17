<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ReturnItem;
use App\Models\Product;
use App\Services\ReturnItemService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class ReturnItemController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService, protected ReturnItemService $returnItemService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->expectsJson()) {
            // Return user's own return items for API requests
            $returnItems = ReturnItem::where('user_id', auth()->id())
                ->with('productBatch.product')
                ->latest()
                ->get();

            return response()->json([
                'data' => $returnItems,
            ]);
        }

        // Admin view - all return items
        $returnItems = ReturnItem::with('productBatch.product', 'customer', 'stockDisposal')->latest()->paginate(15);
        $products = Product::with('batches')->orderBy('item_name')->get();
        $customers = Customer::orderBy('customer_name')->get(['id', 'customer_name']);
        return view('admin.return-items.index', compact('returnItems', 'products', 'customers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * The POS side (pages/return-items.blade.php) only lets a user return
     * from their own Purchase history. Admin isn't limited to that — any
     * product/batch can be picked directly, same pattern as Inventory
     * Adjustment's item search, since an admin-initiated return is usually
     * for a customer who bought via Delivery Receipt/Invoice rather than POS.
     */
    public function create()
    {
        return view('admin.return-items.create', $this->formData());
    }

    /**
     * Data shared by the create and edit-draft forms.
     */
    protected function formData(?ReturnItem $editing = null): array
    {
        $products = Product::with(['genericName', 'batches'])->orderBy('item_name')->get();
        $customers = Customer::orderBy('customer_name')->get(['id', 'customer_name']);

        $productsForJs = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->description ?: $p->item_name,
            'unit' => $p->genericName->unit ?? '',
            'batches' => $p->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
            ])->values(),
        ])->values();

        return compact('customers', 'productsForJs') + ['editingReturnItem' => $editing];
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'product_batch_id' => 'nullable|exists:product_batches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity' => 'nullable|integer|min:1',
            'return_date' => 'nullable|date',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Strict rules for the moment the return is actually submitted —
     * whether that's a direct Save or finalizing a draft, the data must be
     * complete, same shape as the original store() validation.
     */
    protected function postedValidationRules(): array
    {
        return [
            'product_batch_id' => 'required|exists:product_batches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity' => 'required|integer|min:1',
            'return_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());
            $validated['user_id'] = auth()->id();
            $validated['is_draft'] = true;

            $returnItem = ReturnItem::create($validated);

            ActivityLog::record(
                module: 'ReturnItem',
                action: 'draft_saved',
                loggable: $returnItem,
                description: "Saved draft return item #{$returnItem->id}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before submitting.');
            return redirect()->route('return-items.index');
        }

        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Submitting a Return Item is restricted to full admin accounts. Save as Draft instead.');
            return back()->withInput();
        }

        $validated = $request->validate($this->postedValidationRules());
        $validated['user_id'] = auth()->id();
        $validated['status'] = 'pending';
        $validated['is_draft'] = false;

        $returnItem = ReturnItem::create($validated);

        ActivityLog::record(
            module: 'ReturnItem',
            action: 'created',
            loggable: $returnItem,
            description: "Created return item request (qty: {$returnItem->quantity}, reason: {$returnItem->reason})",
        );

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Return item created successfully.',
                'data' => $returnItem,
            ], 201);
        }

        return redirect()->route('return-items.index')
            ->with('success', 'Return item created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnItem $returnItem)
    {
        //
    }

    /**
     * Only a draft can be edited — a posted return item has already gone
     * through (or is waiting on) Approve/Reject, so editing that isn't
     * supported, same policy as every other draft-capable module here.
     */
    public function edit(ReturnItem $returnItem)
    {
        if (! $returnItem->isDraft()) {
            Alert::info('Not supported', 'Editing a submitted Return Item is not supported.');
            return redirect()->route('return-items.index');
        }

        return view('admin.return-items.create', $this->formData($returnItem));
    }

    /**
     * Update the specified resource in storage — resuming/finalizing a draft.
     */
    public function update(Request $request, ReturnItem $returnItem)
    {
        if (! $returnItem->isDraft()) {
            Alert::info('Not supported', 'Editing a submitted Return Item is not supported.');
            return redirect()->route('return-items.index');
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $returnItem->update($validated);

            ActivityLog::record(
                module: 'ReturnItem',
                action: 'draft_updated',
                loggable: $returnItem,
                description: "Updated draft return item #{$returnItem->id}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before submitting.');
            return redirect()->route('return-items.index');
        }

        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Submitting a Return Item is restricted to full admin accounts. Save as Draft instead.');
            return back()->withInput();
        }

        $validated = $request->validate($this->postedValidationRules());
        $validated['status'] = 'pending';
        $validated['is_draft'] = false;

        $returnItem->update($validated);

        ActivityLog::record(
            module: 'ReturnItem',
            action: 'created',
            loggable: $returnItem,
            description: "Submitted return item request #{$returnItem->id} (qty: {$returnItem->quantity}, reason: {$returnItem->reason})",
        );

        return redirect()->route('return-items.index')
            ->with('success', 'Return item created successfully.');
    }

    /**
     * Remove the specified resource from storage. A draft never affected
     * stock or a refund, so deleting it outright is safe; a submitted
     * return item is left exactly as before (unrestricted), since that
     * policy isn't part of this change.
     */
    public function destroy(ReturnItem $returnItem)
    {
        $returnItemId = $returnItem->id;
        $returnItem->delete();

        ActivityLog::record(
            module: 'ReturnItem',
            action: $returnItem->isDraft() ? 'draft_deleted' : 'deleted',
            loggable: $returnItem,
            description: ($returnItem->isDraft() ? 'Deleted draft' : 'Deleted') . " return item #{$returnItemId}",
        );

        return redirect()->route('return-items.index')
            ->with('success', $returnItem->isDraft() ? 'Draft deleted successfully.' : 'Return item deleted successfully.');
    }

    /**
     * Approve a return item and restock the batch.
     */
    public function approve(Request $request, ReturnItem $returnItem)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'refund_method' => 'required|in:credit,cash',
            'stock_disposition' => 'required|in:sellable,write_off',
        ]);

        try {
            // Only approve if status is pending — a draft's status also
            // defaults to 'pending' even though it was never submitted, so
            // that alone can't be the only gate here.
            if ($returnItem->isDraft() || $returnItem->status !== 'pending') {
                return back()->with('error', 'Only pending (submitted) return items can be approved.');
            }

            if (!empty($validated['customer_id'])) {
                $returnItem->customer_id = $validated['customer_id'];
            }

            if ($validated['refund_method'] === 'credit' && !$returnItem->customer_id) {
                return back()->with('error', 'Select a customer to store this return as credit — or choose Cash Refund instead.');
            }

            $result = $this->returnItemService->approve($returnItem, $validated['refund_method'], $validated['stock_disposition'], auth()->id());

            ActivityLog::record(
                module: 'ReturnItem',
                action: 'approved',
                loggable: $returnItem,
                description: "Approved return item #{$returnItem->id} ({$validated['stock_disposition']}, {$validated['refund_method']} refund, {$returnItem->refund_amount})",
            );

            if ($result['creditPayment']) {
                ActivityLog::record(
                    module: 'Customer',
                    action: 'payment_recorded',
                    loggable: $result['creditPayment'],
                    description: "Recorded advance credit of {$result['creditPayment']->amount} for customer {$returnItem->customer->customer_name} from approved Return Item #{$returnItem->id}",
                );
            }

            return back()->with('success', 'Return item approved successfully and stock updated.');
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Exception $e) {
            return back()->with('error', 'Error approving return item: ' . $e->getMessage());
        }
    }

    /**
     * Reject a return item.
     */
    public function reject(Request $request, ReturnItem $returnItem)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        try {
            // Only reject if status is pending — a draft's status also
            // defaults to 'pending' even though it was never submitted, so
            // that alone can't be the only gate here.
            if ($returnItem->isDraft() || $returnItem->status !== 'pending') {
                return back()->with('error', 'Only pending (submitted) return items can be rejected.');
            }

            $rejectionReason = $request->input('rejection_reason', 'No reason provided');

            // Update return item status to rejected with reason
            $returnItem->update([
                'status' => 'rejected',
                'reason' => $returnItem->reason . ' | Rejected: ' . $rejectionReason,
            ]);

            ActivityLog::record(
                module: 'ReturnItem',
                action: 'rejected',
                loggable: $returnItem,
                description: "Rejected return item #{$returnItem->id}" . ($rejectionReason !== 'No reason provided' ? " ({$rejectionReason})" : ''),
            );

            return back()->with('success', 'Return item rejected successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error rejecting return item: ' . $e->getMessage());
        }
    }
}
