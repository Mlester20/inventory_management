<?php

namespace App\Http\Controllers;

use App\Exports\PendingPurchaseItemsExport;
use App\Models\Category;
use App\Models\GenericName;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderSummaryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Purchase Order Summary (Sir's request, "similar to the Sales Order Summary"): a per-supplier
 * summary that drills down into the supplier's Purchase Orders, the item lines behind Pending /
 * Qty Ordered, and the Goods Receipts behind Received.
 */
class PurchaseOrderSummaryController extends Controller
{
    public function __construct(protected PurchaseOrderSummaryService $summary)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $suppliers = $this->summary->supplierSummary($search ?: null);

        return view('admin.purchase-order-summary.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    /** The P.O count link: every Purchase Order of the supplier, whatever its status. */
    public function orders(Request $request)
    {
        $supplier = Supplier::findOrFail($request->validate(['supplier_id' => 'required|integer|exists:suppliers,id'])['supplier_id']);
        $search = trim((string) $request->query('search', ''));

        return view('admin.purchase-order-summary.orders', [
            'supplier' => $supplier,
            'orders' => $this->summary->orders($supplier->id, $search ?: null),
            'search' => $search,
        ]);
    }

    /** The Pending / Qty Ordered links: the item lines of one supplier, added up across Purchase Orders. */
    public function items(Request $request)
    {
        [$supplier, $scope, $search] = $this->itemFilters($request);
        $suppliers = $this->summary->items($scope, $supplier->id, $search ?: null);

        return view('admin.purchase-order-summary.items', [
            'supplier' => $supplier,
            'scope' => $scope,
            'search' => $search,
            'data' => $suppliers->first(),
            'covers' => $this->coversLabel($scope),
        ]);
    }

    public function exportItems(Request $request)
    {
        [$supplier, $scope, $search] = $this->itemFilters($request);
        $suppliers = $this->summary->items($scope, $supplier->id, $search ?: null);

        return Excel::download(
            new PendingPurchaseItemsExport($suppliers),
            'purchase-order-summary-' . $scope . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Same item lines as items(), across every supplier at once — the "P.O Summary - All
     * Suppliers" tab. Same flat shape as the Excel export, just rendered as one on-screen table
     * instead of drilling into one supplier first.
     */
    public function allItems(Request $request)
    {
        [$scope, $search] = $this->allItemFilters($request);
        $suppliers = $this->summary->items($scope, null, $search ?: null);

        return view('admin.purchase-order-summary.all-items', [
            'scope' => $scope,
            'search' => $search,
            'suppliers' => $suppliers,
            'covers' => $this->coversLabel($scope),
        ]);
    }

    public function exportAllItems(Request $request)
    {
        [$scope, $search] = $this->allItemFilters($request);
        $suppliers = $this->summary->items($scope, null, $search ?: null);

        return Excel::download(
            new PendingPurchaseItemsExport($suppliers),
            'purchase-order-summary-all-suppliers-' . $scope . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Every Generic Item's Purchase Order lines across all suppliers, grouped by Generic Item —
     * the "P.O Summary by Item" tab.
     */
    public function byItem(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string|max:100',
        ]);
        $hideZeroBalance = $request->boolean('hide_zero_balance');
        $showZeroOnHand = $request->boolean('show_zero_on_hand');
        $search = trim((string) ($validated['search'] ?? ''));

        return view('admin.purchase-order-summary.by-item', [
            'categories' => Category::orderBy('category_name')->get(),
            'categoryId' => $validated['category_id'] ?? null,
            'hideZeroBalance' => $hideZeroBalance,
            'showZeroOnHand' => $showZeroOnHand,
            'search' => $search,
            'items' => $this->summary->byItem(
                $validated['category_id'] ?? null,
                $hideZeroBalance,
                $showZeroOnHand,
                $search ?: null
            ),
        ]);
    }

    /** The Received link (one Purchase Order line's GRs) and the per-item trace (every GR of a supplier). */
    public function receipts(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'generic_name_id' => 'nullable|integer|exists:generic_names,id',
            'search' => 'nullable|string|max:100',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        return view('admin.purchase-order-summary.receipts', [
            'rows' => $this->summary->receipts(
                $validated['supplier_id'] ?? null,
                $validated['purchase_order_id'] ?? null,
                $validated['generic_name_id'] ?? null,
                $search ?: null
            ),
            'supplier' => isset($validated['supplier_id']) ? Supplier::find($validated['supplier_id']) : null,
            'purchaseOrder' => isset($validated['purchase_order_id']) ? PurchaseOrder::find($validated['purchase_order_id']) : null,
            'genericName' => isset($validated['generic_name_id']) ? GenericName::withTrashed()->find($validated['generic_name_id']) : null,
            'search' => $search,
        ]);
    }

    /** @return array{0: Supplier, 1: string, 2: string} */
    protected function itemFilters(Request $request): array
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'scope' => 'nullable|in:' . PurchaseOrderSummaryService::SCOPE_PENDING . ',' . PurchaseOrderSummaryService::SCOPE_ORDERED,
            'search' => 'nullable|string|max:100',
        ]);

        return [
            Supplier::findOrFail($validated['supplier_id']),
            $validated['scope'] ?? PurchaseOrderSummaryService::SCOPE_PENDING,
            trim((string) ($validated['search'] ?? '')),
        ];
    }

    /** @return array{0: string, 1: string} */
    protected function allItemFilters(Request $request): array
    {
        $validated = $request->validate([
            'scope' => 'nullable|in:' . PurchaseOrderSummaryService::SCOPE_PENDING . ',' . PurchaseOrderSummaryService::SCOPE_ORDERED,
            'search' => 'nullable|string|max:100',
        ]);

        return [
            $validated['scope'] ?? PurchaseOrderSummaryService::SCOPE_PENDING,
            trim((string) ($validated['search'] ?? '')),
        ];
    }

    protected function coversLabel(string $scope): string
    {
        return $scope === PurchaseOrderSummaryService::SCOPE_ORDERED
            ? 'All ordered items'
            : 'Pending items of open Purchase Orders';
    }
}
