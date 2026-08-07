# Customer / Products & Inventory / Delivery Receipt — Progress Tracker

**Read this file first if resuming this work in a new session.** Build-log style — entries are dated and
appended, not overwritten. See `SCHEMA_NOTES.md` for the finalized table structures this file references.

## Current status: All 4 phases already complete — verified against the actual client mockup PDFs, no gaps found (2026-08-03)

---

## Phase breakdown

| Phase | Scope | Status |
|---|---|---|
| 0 | Set up this tracking doc + explore existing code | ✅ Done — 2026-08-03 |
| 1 | Customer module | ✅ Already built (prior session work) — confirmed against mockup 2026-08-03 |
| 2 | Products and Inventory (two-level hierarchy) | ✅ Already built (prior session work) — confirmed against mockup 2026-08-03 |
| 3 | Delivery Receipt | ✅ Already built (prior session work) — confirmed against mockup 2026-08-03 |
| 4 | Wire up Customer derived columns (Advances/Balances/Receivables) | ✅ Already built + shipped this session (branch `feature/customer-credit-flow`) — confirmed against mockup 2026-08-03 |

---

## 2026-08-03 — Full audit against the real mockup PDFs

This prompt arrived requesting a phased rebuild of Customer, Products/Inventory, and Delivery Receipt. Before
writing any code, read both reference PDFs directly (`CFB SALES AND INV SYSTEM_CUSTOMER_SUPPLIER_PRODUCTS.pdf`,
`CFB SALES AND INV SYSTEM_DELIVERY RECEIPT.pdf`) and cross-checked every screen against the actual current
codebase. **Every module described in this prompt already exists**, built across earlier sessions plus this
session's own work (see `feature/customer-credit-flow` branch, 5 commits, already pushed). Nothing in this
audit required new code — this entry records what was checked and confirmed, not what was built.

### Phase 1 — Customer: confirmed complete

- `customers` table has exactly: `customer_name`, `delivery_address`, `contact_number`, `email`,
  `contact_person`, `customer_type` (free text), `price_level`, `vat_type`. Matches the mockup form
  field-for-field (`app/Models/Customer.php`, `resources/views/admin/customers.blade.php`).
- `PRICE_LEVELS` const: `retail => 'Retail (Default)'`, `wholesale => 'Wholesale'`, `price_level_1 => 'P.
  Level 3'`, `price_level_2 => 'P. Level 4'`, `price_level_3 => 'P. Level 5'` — matches the mockup's price
  level list exactly (including the "P. Level 3/4/5" labeling, not "1/2/3").
- `VAT_TYPES` const: `VAT`, `NON-VAT` — matches mockup exactly.
- Customer index columns: Name, Customer Type, Sales Orders, Sales Invoices, Delivery Notes, Advances,
  Balances, Receivables (PHP) — all present and in the same order as the mockup.
- **Confirmed against the mockup screenshot itself** (not just verbal description, which is how this was
  originally built): Advances ("50") and Balances ("20") render as plain integers with no currency
  formatting, styled as clickable/highlighted numbers like the Delivery Notes count — while Receivables
  ("1,000,050.00") is the only column formatted as currency. This is an exact match to what was already
  independently derived and shipped this session (Advances = count of Advance Order DRs, Balances = count
  of unpaid invoices, Receivables = real peso amount) — confirmed correct without having seen this exact
  mockup at build time.

### Phase 2 — Products and Inventory: confirmed complete

- Two-level hierarchy already exists as `GenericName` → `Product` → `ProductBatch` (not literally named
  `generic_items`, see `SCHEMA_NOTES.md` for the naming reconciliation).
- `GenericName`: `code`, `generic_name` (mockup's "Generic Description"), `category_id`, `unit`, `vat_type`
  (const `VAT_TYPES = ['VAT' => 'VAT', 'VAT-EX' => 'VAT-EX']`) — matches the mockup's Generic Item form
  exactly, including the `VAT-EX` wording (confirmed genuinely different from Customer's `NON-VAT` — see the
  naming-inconsistency entry in `SCHEMA_NOTES.md`, left as-is per the earlier confirmed decision this
  session not to unify them).
- `Product`: `code`, `generic_name_id`, `category_id` (auto-synced), `unit` (auto-synced), `item_name`/
  `description`, `barcode`, `unit_cost`, and 5 price tiers each with a `_percent` + amount column
  (`unit_price`, `wholesale_price`, `price_1`, `price_2`, `price_3`), `fda_reg_no`, `fda_reg_exp`,
  `custom_field_1..4` — matches the mockup's Product form exactly, both the 5-tier price grid layout (%
  + Amount columns) and every other field.
- `ProductBatch`: `batch_no` (mockup's "Lot/Batch/Serial"), `expiration_date` — the sole expiry-tracking
  table; `qty`/`reserved_qty` live on `LocationStock` instead (moved there during the earlier location-based
  stock work), with `available_qty` computed (`qty - reserved_qty`), never stored.
- Inventory Items page: 4 tabs already exist (General Item View, Products View, Lot/Serial & Expiry View,
  Product History View) in `InventoryItemsController` + `resources/views/admin/inventory-items/index.blade.php`,
  one shared search bar, columns matching the mockup exactly on every tab.
  - **Newly confirmed against the mockup this session**: General Item View's Qty column is a real link —
    clicks through to the Lot/Serial & Expiry tab pre-filtered to that item (`inventory-items/index.blade.php:271`).
    Lot/Serial & Expiry tab has the "Adjust Inventory" button linking to `inventory-adjustments.create`
    (`inventory-items/index.blade.php:347`) — both exactly as the mockup shows.
- Inventory Adjustment: `inventory_adjustments` (`adjustment_no`, `adjustment_date`, `adjustment_type`,
  `description`, `note`, `prepared_by`) + `inventory_adjustment_lines` (`product_id`, `product_batch_id`,
  `location_id`, `batch_no`, `expiration_date`, `qty`, `remarks`) already exist. `adjustment_type` options
  already defined (`stock_in_found`, `stock_out_damaged`, `stock_out_lost`, `stock_out_expired`,
  `correction_increase`, `correction_decrease`) — the mockup shows the dropdown but not its values, so this
  was originally built without seeing the mockup; no conflict found, nothing to change.
- Product History: already one unified implementation (`InventoryReportService::getProductHistory()`),
  generic over `StockMovement` so it automatically includes Goods Receipt/Delivery Receipt/Inventory
  Adjustment/Stock Transfer/Stock Disposal without per-source union logic — columns match the mockup exactly
  (Date, Transaction, Customer, Supplier, Item Description, Lot/Batch/Serial, Expiry, Qty signed, running
  Balance). No second/conflicting implementation exists to reconcile.

### Phase 3 — Delivery Receipt: confirmed complete

- `delivery_receipts`: `customer_id`, `sales_order_id` (nullable), `dr_no` (reference), `transaction_type`,
  `status` (delivery status), `description`, `receipt_date`, `prepared_by` — matches the mockup.
  `TRANSACTION_TYPES` const: `advance_order => 'ADVANCE ORDER'`, `purchase_order => 'PURCHASE ORDER'`,
  `walk_in => 'WALK-IN'` — exact match, including the dropdown option order shown in the mockup.
- **"PURCHASE ORDER" naming flag — already resolved correctly**: validation on `DeliveryReceiptController::store()`
  requires `sales_order_id` when `transaction_type = purchase_order` (`required_if:transaction_type,purchase_order`),
  validated against `sales_orders`, not the admin Purchase Order module — confirms this transaction type
  really does mean "DR created against an existing Sales Order," exactly the interpretation flagged as
  needing confirmation. No change needed.
- Advance Order flow: `customer_id` required for `advance_order`/`walk_in`, `sales_order_id` forced to
  `null` for those types — matches the mockup's "leave Sales Order Number blank" instruction exactly.
- `invoice_status` is a computed accessor (`NOT INVOICED`/`PARTIALLY INVOICED`/`COMPLETE` derived from
  `invoiced_qty` vs `qty` across lines), never an independently stored/editable field — matches the
  constraint.
- DR create form's cascading search: Generic Description (datalist-based search) → a second `<select>`
  populated with Brand/Batch/Expiry/Stock options per selection (`delivery-receipts/create.blade.php:291,
  322-350`) — functionally matches the mockup's two-level "Generic Description → Brand+Lot/Expiry" combobox
  (implemented as datalist+select rather than a single fancy widget, same UX outcome, no new library).
- DR index columns (Delivery date, Reference, Sales Order No., Customer, Description, Transaction Type,
  Delivery Status, Invoice Status, Timestamp) match exactly — **confirmed the Timestamp column format**
  (`m/d/Y h:i A`, e.g. "07/20/2026 11:52:29 AM") matches the mockup's displayed format.
- DR view/detail page: per-line checkbox (disabled once fully invoiced), Invoiced qty column linking to the
  generated Invoice, Edit/Create Invoice/Delete buttons, Create Invoice acting only on checked lines
  (`line_ids` array) — already built and already live-tested this session (used directly while verifying
  the credit-auto-apply feature: `POST admin/delivery-receipts/{id}/create-invoice` with `line_ids: [...]`).
- Reserved vs. available quantity: **already confirmed and decided earlier this session** —
  `reserved_qty` is dead/unused everywhere (no code increments/decrements it); Sales Order creation does not
  reserve or touch stock at all; Delivery Receipt deducts immediately from Warehouse. This is the app's
  established, intentional behavior (also matches the earlier saved memory: SO is order-only, DR deducts).

### Phase 4 — Customer derived columns: already built and shipped this session

Everything this phase asks for was already implemented, live-tested, and pushed to
`feature/customer-credit-flow` (5 commits) before this prompt arrived:

- **Advances** = count of the customer's Delivery Receipts with `transaction_type = 'advance_order'`
  (`CustomerController::index()`, `deliveryReceipts as advances_count` withCount). Confirmed via the
  mockup that this should be a plain count, not a peso amount — matches exactly.
- **Sales Orders / Sales Invoices / Delivery Notes counts**: all three are real counts today, not stubs —
  `salesOrders`/`deliveryReceipts` via real FKs, `invoices as sales_invoices_count` via the new
  `invoices.customer_id` FK added this session (previously free-text `customer_name` matching only, fixed
  as part of this same effort).
- **Balances** = count of the customer's invoices still carrying an outstanding balance
  (`amount_paid < amount_due`). **Receivables (PHP)** = the real summed peso amount owed across those unpaid
  invoices. Both required fixing `invoices` to have a real `customer_id` FK (added, backfilled by exact
  name match, unmatched rows logged and left null — see `SCHEMA_NOTES.md`) and an `amount_paid` column.
- Additional, beyond what this prompt strictly asked for (from the parallel effort that produced
  `feature/customer-credit-flow`): FIFO payment allocation against oldest unpaid invoices, Return Item →
  customer advance credit (with auto-apply to the customer's next invoice), Available Credit shown in the
  Customer View modal, and Customer management opened to staff (not just admin), mirroring how
  Invoices/Sales Orders/Delivery Receipts already work. See that branch's own commit messages for full
  detail — not re-documented here since it's a separate, already-merged-ready unit of work.

### Nothing left to build

No code changes came out of this audit. If a future session picks this up: check `git log
feature/customer-credit-flow` for the Phase 4 implementation detail, and treat this file as confirmation
that Phases 1-3 needed zero changes as of 2026-08-03 — re-verify against the mockups again only if the
client's actual UI has since diverged from what's described here.
