# Customer / Products & Inventory / Delivery Receipt — Schema Reference

**Status: confirmed complete against the real client mockups (2026-08-03).** This records how the mockup's
requested schema maps onto what actually exists in this codebase — the naming differs from the mockup's
own table names in a few places (the app predates this exact prompt), reconciled below rather than
renamed, since renaming live tables/models for cosmetic alignment isn't worth the migration risk.

## Naming reconciliation (mockup term → actual codebase term)

| Mockup | Actual | Notes |
|---|---|---|
| `generic_items` | `generic_names` (model `GenericName`) | Same concept — code, description, category, unit, vat_type |
| Generic Item's "Generic Description" | `generic_names.generic_name` | Just a column name difference |
| `products` | `products` (model `Product`) | Same table name already |
| `product_batches.lot_batch_serial` | `product_batches.batch_no` | Same concept |
| `product_batches.qty`/`reserved_qty` | `location_stocks.qty`/`reserved_qty` | Moved off the batch during earlier location-based-stock work — a batch's qty is now the sum of its `location_stocks` rows across locations (Warehouse/POS), not a single number on the batch itself |
| `delivery_receipts.reference` | `delivery_receipts.dr_no` | Same concept |
| `delivery_receipts.delivery_status` | `delivery_receipts.status` | Same concept |

## VAT type naming inconsistency — confirmed real, left as-is

- `Customer`/`Supplier`: `VAT_TYPES = ['VAT' => 'VAT', 'NON-VAT' => 'NON-VAT']`
- `GenericName`: `VAT_TYPES = ['VAT' => 'VAT', 'VAT-EX' => 'VAT-EX']`

This exists in the live app exactly as the mockup shows it (Customer/Supplier forms say "NON-VAT", Generic
Item form says "VAT-EX") — confirmed via direct mockup read, not assumed. Decided earlier this session
(user-confirmed): **leave these as two separate concepts, do not unify the labels.** A customer's VAT
registration status and a product's VAT classification are genuinely different things that happen to share
a "VAT" prefix.

## Price tier mapping — confirmed real, already resolved

- `Product` price columns: `unit_price` (Retail), `wholesale_price` (Wholesale), `price_1`, `price_2`,
  `price_3` — mockup labels these **P. Level 1/2/3**.
- `Customer.price_level` options: `retail`, `wholesale`, `price_level_1`, `price_level_2`, `price_level_3`
  — mockup labels the last three **P. Level 3/4/5**.

These are the same 3 underlying tier slots, just numbered differently per form (confirmed by the client
earlier this session, already encoded in `Customer::priceColumn()`):

```php
public function priceColumn(): string
{
    return match ($this->price_level) {
        'wholesale' => 'wholesale_price',
        'price_level_1' => 'price_1',
        'price_level_2' => 'price_2',
        'price_level_3' => 'price_3',
        default => 'unit_price',
    };
}
```

## Delivery Receipt `transaction_type` — confirmed meaning

```php
public const TRANSACTION_TYPES = [
    'advance_order' => 'ADVANCE ORDER',
    'purchase_order' => 'PURCHASE ORDER',
    'walk_in' => 'WALK-IN',
];
```

`PURCHASE ORDER` here means **"this Delivery Receipt was created against an existing Sales Order"** — it
has nothing to do with the admin-side Purchase Order (supplier) module, despite the name collision.
Confirmed via `DeliveryReceiptController::store()`'s validation: `sales_order_id` is `required_if:transaction_type,purchase_order`
and validated against the `sales_orders` table. `ADVANCE ORDER` and `WALK-IN` both require `customer_id`
directly and force `sales_order_id` to null.

## Customer index derived columns — final formulas (Phase 4, shipped this session)

| Column | Formula | Type |
|---|---|---|
| Sales Orders | `count(customer.salesOrders)` | count |
| Sales Invoices | `count(customer.invoices)` | count |
| Delivery Notes | `count(customer.deliveryReceipts)` (any transaction type) | count |
| Advances | `count(customer.deliveryReceipts where transaction_type = 'advance_order')` | count |
| Balances | `count(customer.invoices where amount_paid < amount_due)` | count |
| Receivables (PHP) | `sum(amount_due - amount_paid)` across that customer's unpaid invoices | currency |
| Available Credit (View modal only, not an index column) | `sum(amount - consumed_amount)` across that customer's unconsumed `advance`-type `CustomerPayment` rows | currency |

`invoices.customer_id` (real FK, added this session) replaced the previous free-text `customer_name`
matching — existing rows backfilled by exact name match; any that didn't match are logged and left
`customer_id = null`, excluded from these aggregates going forward (expected/accepted, not a bug).

See branch `feature/customer-credit-flow` for the full implementation: `CustomerPaymentService` (FIFO
payment allocation + advance-credit auto-apply to a customer's next invoice), `ReturnItemService`
(return-to-credit), and the staff-access change to Customer management.

## Inventory Adjustment types — confirmed, no mockup conflict

```php
public const TYPES = [
    'stock_in_found'      => 'Stock In - Found/Recount',
    'stock_out_damaged'   => 'Stock Out - Damaged',
    'stock_out_lost'      => 'Stock Out - Lost/Theft',
    'stock_out_expired'   => 'Stock Out - Expired',
    'correction_increase' => 'Correction - Increase',
    'correction_decrease' => 'Correction - Decrease',
];
```

The mockup shows the Adjustment Type dropdown but not its values — this was built earlier without seeing
the mockup, and nothing in the mockup contradicts it. Left as-is.

## Product History — single implementation, confirmed

`InventoryReportService::getProductHistory()`, generic over the polymorphic `stock_movements` table (every
stock-affecting action — Goods Receipt, Delivery Receipt, Inventory Adjustment, Stock Transfer, Stock
Disposal — already funnels through `StockService`, which writes to `stock_movements`). No separate/
duplicate Product History implementation exists to reconcile.
