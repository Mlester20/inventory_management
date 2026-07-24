<?php

namespace App\Services;

use App\Models\DeliveryReceiptItem;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * Base query joining sales lines to their invoice and item, with the
     * date-range and category filters applied. Rebuilt fresh on every call
     * so the totals/breakdowns below don't share mutated query state.
     */
    protected function baseSalesQuery(?string $startDate, ?string $endDate, ?int $categoryId): Builder
    {
        $query = Sale::query()
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->join('product_batches', 'product_batches.id', '=', 'sales.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id');

        if ($startDate && $endDate) {
            $query->whereBetween('invoices.created_at', [$startDate, "{$endDate} 23:59:59"]);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query;
    }

    /**
     * Aggregate sales summary from the formal Sales Invoice ledger
     * (invoices + sales) over a date range, optionally filtered by category.
     *
     * Scoped to Invoices only — POS quick-sale (`purchases`) and Sales
     * Order / Delivery Receipt volume are separate transaction pathways
     * with their own reports and are not mixed into this total.
     *
     * @return array{total_amount: float, total_qty: int, total_transactions: int, by_item: Collection, by_category: Collection}
     */
    public function getSalesSummary(?string $startDate = null, ?string $endDate = null, ?int $categoryId = null): array
    {
        $totals = $this->baseSalesQuery($startDate, $endDate, $categoryId)
            ->selectRaw('SUM(sales.amount) as total_amount, SUM(sales.qty) as total_qty, COUNT(DISTINCT sales.invoice_id) as total_transactions')
            ->first();

        $byItem = $this->baseSalesQuery($startDate, $endDate, $categoryId)
            ->selectRaw('products.id, products.item_name, SUM(sales.qty) as qty, SUM(sales.amount) as amount')
            ->groupBy('products.id', 'products.item_name')
            ->orderByDesc('amount')
            ->get();

        $byCategory = $this->baseSalesQuery($startDate, $endDate, $categoryId)
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.id, categories.category_name, SUM(sales.qty) as qty, SUM(sales.amount) as amount')
            ->groupBy('categories.id', 'categories.category_name')
            ->orderByDesc('amount')
            ->get();

        return [
            'total_amount' => (float) ($totals->total_amount ?? 0),
            'total_qty' => (int) ($totals->total_qty ?? 0),
            'total_transactions' => (int) ($totals->total_transactions ?? 0),
            'by_item' => $byItem,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Sales grouped by the raw customer_name string on invoices.
     *
     * NOTE: this is a name-based grouping, not a real customer relation.
     * `invoices.customer_name` is free text entered per invoice, so two
     * different spellings of the same customer will not be merged here.
     * A future link from Invoice to the `customers` table could replace
     * this with a proper customer_id-based grouping.
     */
    public function getSalesPerInvoiceCustomerName(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = Invoice::query();

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, "{$endDate} 23:59:59"]);
        }

        return $query
            ->selectRaw('customer_name, SUM(total_sales) as total_spent, COUNT(*) as transactions, MAX(created_at) as last_purchase_at')
            ->groupBy('customer_name')
            ->orderByDesc('total_spent')
            ->get();
    }

    /**
     * Sales grouped by the real Customer record, from delivered Sales Order
     * revenue (qty delivered x the agreed sales_order_items.price).
     *
     * Standalone Advance Order deliveries with no linked Sales Order line
     * carry no price and are not counted here.
     */
    public function getSalesPerCustomerRecord(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DeliveryReceiptItem::query()
            ->join('delivery_receipts', 'delivery_receipts.id', '=', 'delivery_receipt_items.delivery_receipt_id')
            ->join('customers', 'customers.id', '=', 'delivery_receipts.customer_id')
            ->join('sales_order_items', 'sales_order_items.id', '=', 'delivery_receipt_items.sales_order_item_id');

        if ($startDate && $endDate) {
            $query->whereBetween('delivery_receipts.receipt_date', [$startDate, $endDate]);
        }

        return $query
            ->selectRaw('
                customers.id,
                customers.customer_name,
                SUM(delivery_receipt_items.qty * sales_order_items.price) as total_spent,
                COUNT(DISTINCT delivery_receipts.id) as transactions,
                MAX(delivery_receipts.receipt_date) as last_purchase_at
            ')
            ->groupBy('customers.id', 'customers.customer_name')
            ->orderByDesc('total_spent')
            ->get();
    }
}
