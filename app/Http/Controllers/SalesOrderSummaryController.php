<?php

namespace App\Http\Controllers;

use App\Exports\UndeliveredItemsExport;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\SalesOrder;
use App\Services\SalesOrderSummaryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sales Order Summary (Sir's mockup): a per-customer summary that drills down into the
 * customer's Sales Orders, the item lines behind Undelivered / Qty Ordered, and the Delivery
 * Receipts behind Delivered. Replaces the old "Undelivered Items" report.
 */
class SalesOrderSummaryController extends Controller
{
    public function __construct(protected SalesOrderSummaryService $summary)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $customers = $this->summary->customerSummary($search ?: null);

        return view('admin.sales-order-summary.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    /** The S.O count link: every Sales Order of the customer, whatever its status. */
    public function orders(Request $request)
    {
        $customer = Customer::findOrFail($request->validate(['customer_id' => 'required|integer|exists:customers,id'])['customer_id']);
        $search = trim((string) $request->query('search', ''));

        return view('admin.sales-order-summary.orders', [
            'customer' => $customer,
            'orders' => $this->summary->orders($customer->id, $search ?: null),
            'search' => $search,
        ]);
    }

    /** The Undelivered / Qty Ordered links: the item lines of one customer, added up across Sales Orders. */
    public function items(Request $request)
    {
        [$customer, $scope, $search] = $this->itemFilters($request);
        $customers = $this->summary->items($scope, $customer->id, $search ?: null);

        return view('admin.sales-order-summary.items', [
            'customer' => $customer,
            'scope' => $scope,
            'search' => $search,
            'data' => $customers->first(),
            'covers' => $this->coversLabel($scope),
        ]);
    }

    public function exportItems(Request $request)
    {
        [$customer, $scope, $search] = $this->itemFilters($request);
        $customers = $this->summary->items($scope, $customer->id, $search ?: null);

        return Excel::download(
            new UndeliveredItemsExport($customers),
            'sales-order-summary-' . $scope . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /** The Delivered link (one Sales Order line's PO) and the per-item trace (every PO of a customer). */
    public function deliveries(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'generic_name_id' => 'nullable|integer|exists:generic_names,id',
            'search' => 'nullable|string|max:100',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        return view('admin.sales-order-summary.deliveries', [
            'rows' => $this->summary->deliveries(
                $validated['customer_id'] ?? null,
                $validated['sales_order_id'] ?? null,
                $validated['generic_name_id'] ?? null,
                $search ?: null
            ),
            'customer' => isset($validated['customer_id']) ? Customer::find($validated['customer_id']) : null,
            'salesOrder' => isset($validated['sales_order_id']) ? SalesOrder::find($validated['sales_order_id']) : null,
            'genericName' => isset($validated['generic_name_id']) ? GenericName::withTrashed()->find($validated['generic_name_id']) : null,
            'search' => $search,
        ]);
    }

    /** @return array{0: Customer, 1: string, 2: string} */
    protected function itemFilters(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'scope' => 'nullable|in:' . SalesOrderSummaryService::SCOPE_UNDELIVERED . ',' . SalesOrderSummaryService::SCOPE_ORDERED,
            'search' => 'nullable|string|max:100',
        ]);

        return [
            Customer::findOrFail($validated['customer_id']),
            $validated['scope'] ?? SalesOrderSummaryService::SCOPE_UNDELIVERED,
            trim((string) ($validated['search'] ?? '')),
        ];
    }

    protected function coversLabel(string $scope): string
    {
        return $scope === SalesOrderSummaryService::SCOPE_ORDERED
            ? 'All ordered items (open and completed Sales Orders)'
            : 'Undelivered items of open Sales Orders';
    }
}
