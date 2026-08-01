# Prompt: Implement Location-Based Stock (Warehouse → POS Transfer) — Laravel 12 + Blade
## Task

This is a **new module**: multi-location stock tracking. Right now, stock (`qty` on `product_batches`) is
treated as one single global number, and **POS reads directly from that same number** — so a product's
total inventory and its POS-sellable stock are the same thing. That's wrong for how the business actually
works.

**Target behavior:**

- There is a **Warehouse** location, which holds the main/bulk stock (this is effectively what
  `product_batches.qty` represents today).
- There is a **POS** location (its own, separate stock bucket).
- A new **Stock Transfer** transaction moves quantity from Warehouse → POS (or, generally, from one location
  to another, in case more locations/branches are added later). Transferring **deducts from the source
  location's stock and adds to the destination location's stock** — it does not create or destroy stock,
  only moves it between locations.
- **POS must read its sellable/available stock from the POS location's stock, not from the Warehouse total
  or the old global `qty`.** A product with 1,500 units in the Warehouse and 0 transferred to POS should show
  0 available in POS until a transfer moves stock there.

Example from the actual business flow: Biogesic has 1,500 units in Warehouse. Transferring 500 units to POS
should result in Warehouse = 1,000, POS = 500 — and POS's item lookup/barcode scan/available-qty checks
should only ever see that 500, not the full 1,500.

## Step 1 — Explore before building anything

1. **Before designing any new table, read the actual current Inventory Items flow end-to-end**: the
   `product_batches` migration (exact columns as they exist today, not as assumed), the Controller/Service
   behind the Inventory Items page's four tabs (General Item View, Products View, Lot/Serial & Expiry View,
   Product History View), and the Blade views themselves. Don't design `location_stocks` or any new schema
   until you've confirmed exactly how `product_batches.qty`/`reserved_qty` are read and written today.
2. **Read the existing Product History view/logic specifically** — it already exists and already traces
   movements (Goods Receipt in, Delivery Receipt out, Inventory Adjustment) per lot/batch with a running
   balance. Whatever you build for Stock Transfer must plug into this **same** ledger/history mechanism as
   another movement type, not a separate, disconnected transfer log — a Stock Transfer needs to show up in
   Product History exactly like the other transaction types do, so movement stays fully traceable in one
   place.
3. From that reading, list out every other place that reads or writes `product_batches` stock — this almost
   certainly includes the Products/Inventory views (Lot/Serial & Expiry tab, Products tab Qty/Reserved/
   Available columns), Inventory reports, the POS barcode scanning + FEFO logic built earlier, and Delivery
   Receipt / Goods Receipt / Inventory Adjustment flows. **All of these currently assume one global stock
   number and will need to become location-aware** — list them out for me before changing code, since this
   affects a lot of already-working features.
4. Confirm how Goods Receipt currently adds stock (presumably directly into `product_batches.qty`) — once
   locations exist, Goods Receipt should almost certainly add stock **into the Warehouse location** by
   default, not into an ambiguous global pool.

## Step 2 — Schema design

1. **`locations`** table: `name` (e.g. "Warehouse", "POS"), plus whatever else is standard for this app's
   other reference tables (timestamps, maybe an `is_default` or `type` flag if useful for seeding/logic).
   Seed at least `Warehouse` and `POS` as the two initial locations.
2. **Location-aware stock**: rather than adding a `location_id` directly onto `product_batches` (which would
   force one batch/lot to live in only one location, breaking the "same lot split across two locations"
   case), add a new pivot-style table: **`location_stocks`** — `location_id`, `product_batch_id`, `qty`,
   `reserved_qty` (mirroring the existing reserved/available concept, now per-location). A `product_batches`
   row keeps representing "this lot of this product with this expiry exists," while `location_stocks` rows
   say how much of it sits where. Sum of a batch's `location_stocks.qty` across all locations should always
   equal what the batch's own total is — decide with me whether `product_batches.qty` should become a
   denormalized total (kept in sync) or be dropped entirely in favor of always summing `location_stocks`.
3. **`stock_transfers`**: `date`, `reference`, `from_location_id`, `to_location_id`, `status` (e.g.
   `PENDING`/`COMPLETED` if transfers need a confirmation step, or skip status if transfers should apply
   immediately — ask me which before building). `stock_transfer_lines`: `product_batch_id`, `qty`.
4. Migrate existing data: on migration, create the `Warehouse` location and move all current
   `product_batches.qty` into `location_stocks` rows against `Warehouse`, with `POS` starting at 0 for every
   product. Write this as an actual data migration step, not just a schema migration, so existing stock
   isn't lost or duplicated.

## Step 3 — Stock Transfer feature (new UI)

Follow this app's existing transaction-document patterns (Goods Receipt / Delivery Receipt / Inventory
Adjustment) for consistency:

- **Stock Transfer index** — list of past transfers: date, reference, from, to, status, prepared by.
- **Stock Transfer create/edit form** — date/reference auto-filled, From Location and To Location dropdowns
  (populated from `locations`), a repeatable line table (search product/batch by Generic Description → Brand
  → Lot, same cascading pattern used in Delivery Receipt, auto-filling available qty at the source location
  so the user can see what's actually transferable), Qty per line, Save.
- On save: validate each line's qty doesn't exceed the source location's current available qty for that
  batch (can't transfer more than what's in the Warehouse) before deducting/adding.
- Each completed transfer must write into the **existing Product History ledger mechanism** (from Step 1.2)
  as a `Stock Transfer` movement type, showing the deduction at the source location and the addition at the
  destination — reuse whatever the existing Goods Receipt/Delivery Receipt/Inventory Adjustment code does to
  write a history entry, don't build a second logging path.

## Step 4 — Update everything that reads stock

Go through the list you built in Step 1 and update each to be location-scoped:

- **POS** (item search, barcode scan lookup, FEFO batch selection, cart validation) — must only consider
  `location_stocks` rows for the **POS** location.
- **Products/Inventory views** — decide with me whether the existing Qty/Reserved/Available columns should
  now default to showing Warehouse totals, POS totals, or a combined view with a location filter/toggle —
  this is a real UX decision, not just a query change, so confirm before implementing.
- **Product History ledger** — add a Location column so movements (Goods Receipt in, Stock Transfer out/in,
  Delivery Receipt out, Inventory Adjustment) are traceable per location, not just per product.
- **Inventory Report / low-stock threshold logic** — confirm whether low-stock alerts should evaluate
  against Warehouse stock, POS stock, or total — ask me, don't assume, since this directly affects the
  dashboard's low-stock warning banner.
- **Reserved quantity logic** — if reservations exist (e.g. from Sales Orders), confirm which location a
  reservation should be scoped to.

## Constraints

- Don't introduce a new UI framework or package — this follows the same transaction-document + Service/
  Controller pattern already used for Goods Receipt/Delivery Receipt/Inventory Adjustment.
- Do not lose or double-count existing stock during the Warehouse migration — this touches real inventory
  numbers, so the data migration step needs to be correct, not approximate.
- Flag every place in Step 1's audit where you're unsure whether "stock" should now mean Warehouse, POS, or
  total — wrong assumptions here directly cause overselling in POS or false low-stock alerts.