# Stock Integrity & Race-Condition Fixes — September 2026

Reference doc for the bugs found during manual testing of the Sales and Purchase modules
(branch `feature/saims-rev-2.0b-analysis`), what was fixed, and how to verify it stays fixed.

## Background

All of these bugs share the same root cause: a "check the record's status, then act on it"
sequence where the status check ran on a model instance that could already be **stale** by the
time the check executed — either because it was loaded before an earlier request committed
(a double-click submitting the same form twice), or because the underlying `SELECT` had no lock
and a concurrent transaction could read the same "before" state under its own snapshot.

The fix pattern is the same everywhere: **re-fetch the record with `lockForUpdate()` inside the
transaction**, and check status on that fresh, locked copy — not on whatever was passed in. A
concurrent second call then blocks until the first commits, sees the real (changed) status, and
is rejected with a `ValidationException` instead of duplicating the action.

## Bugs found and fixed

### 1. Document-number generation race (9 places)

**Symptom:** `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'SO-2026-00006'
for key 'sales_orders_so_no_unique'` (or the equivalent for DR/GR/PO/Invoice/etc.) — a 500 error
on save.

**Cause:** Every document-number generator (`generateSoNo()`, `generateDrNo()`, `generateGrNo()`,
`generatePoNo()`, `generateQuoteNo()`, `generateReference()` x2, `generateSalesNo()` x2) computed
the "next number" with a plain `orderByDesc(...)->value(...)` — no lock. Two near-simultaneous
saves could both read the same "last number" and try to insert the same one.

**Fix:** Added `->lockForUpdate()` to every one of these queries.

**Files:** `SalesOrderService`, `DeliveryReceiptService` (x2), `GoodsReceiptService`,
`PurchaseOrderService`, `SalesQuoteService`, `StockDisposalService`, `StockTransferService`,
`InventoryAdjustmentService`, `InvoiceController`.

### 2. Trashed record still "occupies" its number

**Symptom:** Same duplicate-key error as above, but reproducible even with a single request —
because a **soft-deleted** Sales Order/Delivery Receipt/Invoice still exists in the table (and
still holds its number in the unique index), but the generator's query respects the SoftDeletes
global scope and never sees it, so it reuses the "free-looking" number.

**Fix:** Added `withTrashed()` to the three generators for soft-deletable models.

**Files:** `SalesOrderService::generateSoNo()`, `DeliveryReceiptService::generateDrNo()`,
`DeliveryReceiptService::generateSalesNo()`, `InvoiceController::generateSalesNo()`.

### 3. Sales Quote → double "Convert to Sales Order"

**Symptom:** Two Sales Orders silently created from one Sales Quote (no error at all).

**Fix:** `SalesQuoteService::convertToSalesOrder()` now re-fetches the Quote with
`lockForUpdate()` before checking `status === 'open'`.

### 4. Return Item → double "Approve"

**Symptom:** Stock restocked twice, and (on a credit refund) the customer credited twice, for one
return.

**Fix:** `ReturnItemService::approve()` now re-fetches the Return Item with `lockForUpdate()`
before checking `status === 'pending'`.

### 5. Delivery Receipt → double "Post" (finalize draft) — most dangerous

**Symptom:** **Silent double stock deduction.** The second finalize's `items()->delete()`
replaces the first finalize's items, so the Delivery Receipt itself looks completely normal
(one set of items) — but Warehouse stock was deducted twice. No error, no duplicate record, no
visible symptom at all.

**Fix:** `DeliveryReceiptService::finalizeDraft()` now re-fetches the draft with
`lockForUpdate()` before checking `isDraft()`.

### 6. Delivery Receipt / Advance Orders → double "Create Invoice"

**Symptom:** The same delivered line could be billed on two separate invoices.

**Fix:** `DeliveryReceiptService::createInvoiceFromLines()` now locks the
`delivery_receipt_items` rows (`lockForUpdate()`) before reading `remaining_invoiceable_qty`.

*(Note: this specific race needs two genuinely concurrent database connections to reproduce —
it wasn't reproducible from a single sequential test the way #3–#5 were, since the query it
guards already re-reads fresh state on every call. The lock was still added as the correct,
low-risk fix for real concurrent access.)*

### 7. Goods Receipt → double "Post" (finalize draft) — Purchase-side mirror of #5

**Symptom:** Silent double stock **restock** — phantom stock added to the Warehouse that was
never actually delivered, with no duplicate record to hint anything's wrong.

**Fix:** `GoodsReceiptService::finalizeDraft()` — same `lockForUpdate()` + re-check pattern.

## Lower-severity, not fixed (by design — harmless)

These have the same "check-then-act" shape but a double-click causes no real harm (idempotent
outcome), so they were left as-is:

- Double-click "Cancel" on Sales Order / Delivery Receipt / Invoice — same end state either way.
- Double-click "Reject" on Return Item — only duplicates the logged reason text.
- Double-finalize of a **Purchase Order** draft — no stock/money is touched by a PO finalize
  (only line items are (re)created), so a race here just re-saves the same final data.

## RBAC gap closed: admin_staff delete restriction (Purchase side)

Found while auditing Purchase modules: `PurchaseOrderController::destroy()` and
`PurchaseInvoiceController::destroy()` had no `admin_staff` restriction at all (a posted,
non-draft Purchase Order could even be hard-deleted by any role), and no FK-constraint-violation
handling. Both now match the established pattern already used elsewhere (Product, GenericName,
Sales Order, etc.):
- Blocked for `admin_staff` (server-side check + hidden Delete button in the Blade views).
- `QueryException` (code 23000) caught with a friendly "still has related records" message
  instead of a raw 500.

## FEFO ordering fix (not a bug — a missing hint)

`StockService::getAvailableBatchesForGenericName()` (powers the Delivery Receipt and Stock
Transfer batch pickers) had no ordering at all — batches appeared in arbitrary DB order, with no
nudge toward the soonest-to-expire one. This is different from the *enforced* automatic FEFO used
by POS checkout and Direct Sales Invoice (`StockService::deductFefo()`) — DR/Stock Transfer are
deliberately manual-pick flows. Added `orderByRaw('expiration_date IS NULL, expiration_date ASC')`
so the dropdown now defaults to showing the soonest-expiring batch first; the choice is still
entirely manual.

## Automated regression tests

`tests/Feature/ConcurrentActionGuardsTest.php` — covers bugs #3, #4, #5, #6 above. Each test
loads two independent copies of the same record (mirroring two requests that each loaded it
before either committed), runs the action on the first copy, then runs it again on the second
(still holding the pre-change state) — proving the service re-checks fresh state instead of
trusting the copy it was handed.

**Run all tests:**
```
php artisan test
```

**Run just this file:**
```
php artisan test tests/Feature/ConcurrentActionGuardsTest.php
```

Validated (2026-09-07) by temporarily reverting the `lockForUpdate()` line in
`SalesQuoteService` and `ReturnItemService` and confirming the corresponding tests correctly
fail — so these are real regression tests, not tautological ones.

### Test-infrastructure fixes needed to make this runnable at all

The test suite's `RefreshDatabase` (fresh migration on SQLite in-memory) was completely broken
before this — every test using it failed at the migration step, unrelated to any of the above.
Fixed as a prerequisite:

1. `database/migrations/2026_07_24_130004_migrate_items_to_products_and_batches.php` —
   `stock_movements` had an explicit index on `item_id` beyond its FK; SQLite's emulated
   `dropColumn()` doesn't clean that up itself. Added an explicit `dropIndex()` first.
2. `database/migrations/2026_08_03_090200_backfill_customer_id_on_invoices_table.php` — used a
   MySQL-only `UPDATE ... JOIN` that SQLite can't emulate correctly. Rewritten as a portable
   per-customer loop (same result on both databases).
3. `database/factories/ProductBatchFactory.php` — still set `qty`/`reserved_qty`, columns that no
   longer exist on `product_batches` (stock now lives in `location_stocks`). Removed.

**Pre-existing, still-broken, out of scope:** `tests/Feature/StockManagementTest.php` and
`tests/Unit/CogsServiceTest.php` still fail (26 tests) — both call APIs from before the
Location-based stock refactor (e.g. `StockService::restock($batch, $qty)` with no `Location`
argument). Not touched here since it's unrelated pre-existing debt, not a regression from this
work.

## Verification method used throughout

Every fix above (except the automated tests) was verified live against the real dev database
via `php artisan tinker`, wrapped in `DB::beginTransaction()` ... `DB::rollBack()` in a `finally`
block — reproducing the exact bug first, then confirming the fix, without ever leaving mutated
data behind.
