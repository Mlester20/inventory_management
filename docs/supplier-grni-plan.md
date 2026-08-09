# Supplier "Advances" → GRNI — Implementation Plan

**Status: planned, not yet built.** Branch: `feature/supplier-unbilled-receipts`.

## Context

The Supplier list's existing "Advances" column is purely a payment-ledger figure — the sum of
`SupplierPayment` rows recorded with `type = 'advance'` ("prepayment for a future PO"). Confirmed with
the client (via Sir) that this is the wrong concept for that slot. What's actually wanted, mirroring the
Customer module's "Advances" (count of Delivery Receipts with no Purchase Order yet), is the supplier-side
equivalent: **Goods Receipts that have been received but have no Purchase Invoice yet** — i.e. stock is
already in the Warehouse, but the supplier hasn't billed for it.

Note: the client's original mockup for the Supplier list (`CFB SALES AND INV SYSTEM_CUSTOMER_SUPPLIER_PRODUCTS.pdf`)
never actually had an "Advances" column at all — Name / Purchase Orders / Purchase Invoices / Goods
Receipts / Balances / Payables (PHP). This column was added later, copying the Customer module's pattern
without re-checking whether it fit Supplier's actual needs. This plan replaces it with something that does.

## Confirmed term: **GRNI** (Goods Received Not Invoiced)

Standard procurement/accounting term for exactly this concept (also called GR/IR clearing in some ERPs).
Confirmed with the user over the alternatives (Pending Invoices, Unbilled Receipts).

## Definition

**GRNI count** = number of that Supplier's `GoodsReceipt` rows with **no** `PurchaseInvoice` referencing
them (`purchase_invoices.goods_receipt_id`).

This mirrors Customer's Advances exactly: a count, not a peso amount — matching how "Advances"/"Balances"
render on the Customer list (plain numbers, not currency-formatted; only "Receivables"/"Payables (PHP)" are
money).

Not currently tracked: partial billing at the GR line level (unlike Delivery Receipt → Invoice, which has
`invoiced_qty` per line). So this is binary per GR — has at least one linked Purchase Invoice, or none —
not a partial/fractional state. If the client's actual GR→Purchase Invoice flow supports partial billing
later, this would need revisiting; flagging now so it isn't assumed settled.

## Schema

No migration needed — `purchase_invoices.goods_receipt_id` (nullable FK) already exists. Just need the
inverse relation:

```php
// app/Models/GoodsReceipt.php
public function purchaseInvoices(): HasMany
{
    return $this->hasMany(PurchaseInvoice::class);
}
```

(`HasMany`, not `HasOne` — the FK isn't unique-constrained, so nothing stops more than one invoice
referencing the same GR in principle; "GRNI" just means zero, regardless of whether one or several could
exist.)

## Controller change — `SupplierController::index()`

Replace the current `$supplier->advances = $totalAdvances` (payment-based) with a GRNI count:

```php
$suppliers = Supplier::withCount([
        'purchaseOrders', 'goodsReceipts', 'purchaseInvoices',
        'goodsReceipts as grni_count' => fn ($q) => $q->doesntHave('purchaseInvoices'),
    ])
    ->with(['payments' => fn ($q) => $q->latest('payment_date')->limit(5)])
    ->orderBy('supplier_name')
    ->paginate(15);
```

`$totalAdvances` (the payment-ledger figure) is **not deleted** — the ability to record a real advance
payment to a supplier is a legitimate feature on its own, independent of what the list column shows.
Recommend: keep it out of the list entirely (it's not one of the mockup's original columns anyway), but
keep it visible in the View modal's payment detail section, relabeled clearly (e.g. "Advance Payments on
File") so it doesn't collide with the new GRNI concept in the same modal.

## UI change — `resources/views/admin/supplier.blade.php`

- Rename the "Advances" column header to **"GRNI"** (with a hover tooltip explaining it, matching the
  pattern already used on the Customer list's Advances/Balances headers after that exact same kind of
  confusion came up there).
- Change the cell from `number_format($supplier->advances, 2)` (currency) to a plain count
  (`$supplier->grni_count`), styled as a badge matching Customer's Advances treatment.
- View modal: keep the existing peso "Advances" figure but relabel it, and add the GRNI count alongside it
  as its own stat, same layout pattern as Customer's Advances/Balances/Receivables/Available Credit row.
- Consider linking the GRNI count to a filtered Goods Receipts list (e.g.
  `goods-receipts.index?supplier_id=X&uninvoiced=1`) — needs a small addition to
  `GoodsReceiptController::index()` to support that filter; flagging as a nice-to-have, not required for
  the column itself to work.

## Related, NOT in scope for this task (flagging only)

Supplier's existing "Balances" and "Payables (PHP)" use the same kind of single-combined-formula pattern
Customer's did before that got fixed (`payables - payments - advances` in one number, rather than a real
per-invoice paid/unpaid count + accurate outstanding total). If the client wants the same
count-of-unpaid-invoices / real-payables-total treatment Customer got, that's a separate, larger piece of
work (would need a `purchase_invoices.amount_paid` column + FIFO payment application, mirroring
`CustomerPaymentService`) — not assumed here, just noted so it isn't missed later.

## Verification plan

1. `php -l` on every changed file.
2. Live HTTP test via a temp admin account: create a test Supplier, a Goods Receipt against it with no
   Purchase Invoice (confirm GRNI count = 1), then create a Purchase Invoice against that same Goods
   Receipt (confirm GRNI count drops back to 0). Confirms the count reacts correctly in both directions.
3. Confirm the existing "Advances" peso figure (SupplierPayment-based) still works correctly wherever it's
   kept (View modal), unaffected by the list-column change.
4. Clean up all test records, verify deletion via explicit existence checks afterward.
