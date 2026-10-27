<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeliveryReceipt;
use App\Models\ProductBatch;
use App\Models\ProductReturn;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ReturnItem;
use App\Models\Product;
use App\Services\ReturnItemService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /** The reasons offered on a return, per line on the Delivery Receipt form. */
    public const REASONS = ['Defective Product', 'Wrong Item Received', 'Not as Described', 'Changed Mind', 'Damaged During Delivery', 'Other'];

    /**
     * Return several items at once: pick the customer and a Delivery Receipt and every line of it is
     * listed, ready for a return quantity and a reason of its own (one may be damaged, another the
     * wrong item).
     */
    public function createFromDeliveryReceipt(Request $request)
    {
        $customers = Customer::orderBy('customer_name')->get(['id', 'customer_name']);
        $preselected = $request->query('delivery_receipt_id')
            ? DeliveryReceipt::with('customer')->find($request->query('delivery_receipt_id'))
            : null;

        return view('admin.return-items.from-delivery-receipt', [
            'customers' => $customers,
            'preselected' => $preselected,
            'reasons' => self::REASONS,
        ]);
    }

    /**
     * One Return Item per line that has a return quantity, each with its own reason. All or nothing: if
     * any line is refused (more than was delivered, ...) none is created.
     */
    public function storeFromDeliveryReceipt(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Submitting a Return Item is restricted to full admin accounts.');
            return back()->withInput();
        }

        $validated = $request->validate([
            'delivery_receipt_id' => 'required|exists:delivery_receipts,id',
            'return_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'lines' => 'required|array',
            'lines.*.qty' => 'nullable|integer|min:0',
            'lines.*.reason' => 'nullable|string|max:255',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        $deliveryReceipt = DeliveryReceipt::findOrFail($validated['delivery_receipt_id']);

        $lines = collect($validated['lines'])->filter(fn ($line) => (int) ($line['qty'] ?? 0) > 0);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Enter a return quantity for at least one item.']);
        }

        foreach ($lines as $batchId => $line) {
            if (trim((string) ($line['reason'] ?? '')) === '') {
                $name = ProductBatch::with('product')->find($batchId)?->product?->display_description ?? "item {$batchId}";
                throw ValidationException::withMessages(['lines' => "Choose a reason for {$name}."]);
            }
        }

        $productReturn = null;
        $created = DB::transaction(function () use ($lines, $validated, $deliveryReceipt, &$productReturn) {
            $productReturn = ProductReturn::create([
                'return_no' => ProductReturn::nextNumber(),
                'delivery_receipt_id' => $deliveryReceipt->id,
                'customer_id' => $deliveryReceipt->customer_id,
                'return_date' => $validated['return_date'],
                'prepared_by' => $validated['prepared_by'] ?? null,
                'user_id' => auth()->id(),
            ]);

            return $lines->map(function ($line, $batchId) use ($validated, $deliveryReceipt, $productReturn) {
                $data = $this->resolveSource([
                    'delivery_receipt_id' => $deliveryReceipt->id,
                    'customer_id' => $deliveryReceipt->customer_id,
                    'product_batch_id' => (int) $batchId,
                    'quantity' => (int) $line['qty'],
                    'return_date' => $validated['return_date'],
                    'reason' => $line['reason'],
                    'notes' => $line['notes'] ?? null,
                    'prepared_by' => $validated['prepared_by'] ?? null,
                ]);
                $data['user_id'] = auth()->id();
                $data['status'] = 'pending';
                $data['is_draft'] = false;
                $data['product_return_id'] = $productReturn->id;

                return ReturnItem::create($data);
            })->values();
        });

        foreach ($created as $returnItem) {
            ActivityLog::record(
                module: 'ReturnItem',
                action: 'created',
                loggable: $returnItem,
                description: "Created {$productReturn->return_no} line from {$deliveryReceipt->dr_no} (qty: {$returnItem->quantity}, reason: {$returnItem->reason})",
            );
        }

        return redirect()->route('product-returns.show', $productReturn)
            ->with('success', "{$productReturn->return_no} created from {$deliveryReceipt->dr_no}. Approve it to put the stock back.");
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
            'delivery_receipt_id' => 'nullable|exists:delivery_receipts,id',
            'prepared_by' => 'nullable|exists:users,id',
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
            'delivery_receipt_id' => 'nullable|exists:delivery_receipts,id',
            'prepared_by' => 'nullable|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'return_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * A return that names a Delivery Receipt has to be one that really delivered this item to this
     * customer, and cannot give back more than that DR delivered minus what was already returned
     * against it. A return with no DR (POS / direct invoice) is left exactly as before.
     */
    protected function resolveSource(array $validated, ?ReturnItem $existing = null): array
    {
        if (empty($validated['delivery_receipt_id'])) {
            $validated['delivery_receipt_id'] = null;

            return $validated;
        }

        $deliveryReceipt = DeliveryReceipt::with('items.productBatch')->findOrFail($validated['delivery_receipt_id']);
        $batch = ProductBatch::find($validated['product_batch_id'] ?? null);

        if ($deliveryReceipt->isDraft() || $deliveryReceipt->isCancelled()) {
            throw ValidationException::withMessages(['delivery_receipt_id' => 'Pick a posted Delivery Receipt that was not cancelled.']);
        }

        if (! empty($validated['customer_id']) && (int) $deliveryReceipt->customer_id !== (int) $validated['customer_id']) {
            throw ValidationException::withMessages(['delivery_receipt_id' => 'That Delivery Receipt belongs to another customer.']);
        }

        if ($batch) {
            $delivered = (int) $deliveryReceipt->items
                ->filter(fn ($line) => $line->productBatch?->product_id === $batch->product_id)
                ->sum('qty');

            if ($delivered === 0) {
                throw ValidationException::withMessages(['delivery_receipt_id' => "{$deliveryReceipt->dr_no} did not deliver this item."]);
            }

            $alreadyReturned = (int) ReturnItem::where('delivery_receipt_id', $deliveryReceipt->id)
                ->where('is_draft', false)
                ->where('status', '!=', 'rejected')
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->whereHas('productBatch', fn ($q) => $q->where('product_id', $batch->product_id))
                ->sum('quantity');

            $available = $delivered - $alreadyReturned;
            if ((int) $validated['quantity'] > $available) {
                throw ValidationException::withMessages(['quantity' => "Only {$available} of this item can still be returned against {$deliveryReceipt->dr_no} ({$delivered} delivered, {$alreadyReturned} already returned)."]);
            }
        }

        // No customer picked: the DR knows who it was delivered to.
        $validated['customer_id'] = $validated['customer_id'] ?? $deliveryReceipt->customer_id;

        return $validated;
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

        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Submitting a Return Item is restricted to full admin accounts. Save as Draft instead.');
            return back()->withInput();
        }

        $validated = $this->resolveSource($request->validate($this->postedValidationRules()));
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

        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Submitting a Return Item is restricted to full admin accounts. Save as Draft instead.');
            return back()->withInput();
        }

        $validated = $this->resolveSource($request->validate($this->postedValidationRules()), $returnItem);
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
            'refund_method' => 'required|in:credit,cash,none',
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

            // Goods from a Delivery Receipt that was never invoiced are not refunded, whatever was picked.
            if (! $returnItem->isRefundable()) {
                $validated['refund_method'] = 'none';
            } elseif ($validated['refund_method'] === 'none') {
                return back()->with('error', 'Choose Cash Refund or Store Credit for goods that were already invoiced.');
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
