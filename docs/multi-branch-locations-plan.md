# Multi-Branch Locations — Implementation Plan

**Status: DISREGARDED (2026-08-11), after client meeting.** Turned out each branch runs its own separate
system entirely — "kanya kanya branch," not shared stock buckets under this one SAIMS install. Only the main
branch uses this POS/inventory system; the other branches aren't part of it. This whole plan (branch-scoped
locations, `users.location_id`, per-branch `Location::pos()` resolution) does not apply — leaving the doc in
place only as a record of the investigation, not as work to resume.

## Context

Today `locations` has exactly 2 rows (Warehouse, POS), inserted by a one-off data migration — there's no
admin screen to add more. The client has multiple real branches (Baguio, Benguet, Manila, etc.) and wants
each one to be its own stock bucket: Warehouse → Stock Transfer → a specific branch, and once stock lands
at a branch, checkout there only ever sees that branch's own stock (not a combined "POS" total, not other
branches').

## What's already correct, needs no change

- **`location_stocks`** (location_id, product_batch_id, qty, reserved_qty) is already a proper per-location
  ledger — not hardcoded to 2 locations. Adding a 3rd, 10th, 50th location needs zero schema change here.
- **`stock_transfers`** (`from_location_id`, `to_location_id`) is already fully generic — a transfer already
  works between any two locations, not just Warehouse→POS specifically. Confirmed: nothing to change here.
- **`Location::warehouse()`** looks up `is_default = true`, not the literal name "Warehouse" — this already
  works correctly regardless of how many other locations exist.

## What actually breaks / is missing

1. **`Location::pos()`** looks up `where('name', 'POS')` — this only works because there's exactly one row
   literally named "POS". Once branches are named "Baguio", "Benguet", etc., there is no longer *a* POS
   location — there are many. Every call site using this helper needs to resolve "which branch" some other
   way (see below).
2. **No way to create a branch.** No `LocationController`, no route, no admin page. Adding a branch today
   means me running a migration by hand — not something the client can self-serve.
3. **`users` has no branch assignment at all.** Nothing ties a staff login to "which branch do they work
   at."

## Confirmed direction

- **`users.location_id`** (nullable FK to `locations`) — assigns a POS/staff account to one branch. Null
  for admin accounts (they're not tied to one branch) unless a specific admin also mans a counter day to
  day, in which case they'd get one too and the same resolution logic applies to them.
- A real **Locations admin CRUD** (index/create/edit) needs to be built so the client can add branches
  themselves going forward — Baguio, Benguet, Manila, and whatever comes after, without a code change each
  time.

## The 9 call sites that reference `Location::pos()` today, by how they need to change

**Bucket 1 — straightforward: swap for "current user's assigned branch"**
- `Api/ItemController` (item search, barcode lookup, quantity check — 3 call sites)
- `Api/PurchaseController` (POS checkout stock deduction)
- `HomeController` (POS staff dashboard low-stock alert — already built generically via
  `DashboardService::getLowStockAlert($locationId)`, this is a one-line change)

**Bucket 2 — needs a business-rule decision, not just a swap**
- `ReturnItemService` currently restocks a return to `Location::pos()`. For multi-branch, a return should
  arguably go back to wherever the *original sale* happened — not wherever the staff processing the return
  happens to be standing right now (could be a different branch, or even head office). That means tracing
  back through the original Purchase/batch/movement to find the source location, not just reading the
  current user's `location_id`. **Needs confirmation**: is that actually the rule, or should a return always
  just restock to whichever branch is processing it?

**Bucket 3 — needs real UI/UX design, not just a code swap**
- `InventoryItemsController`'s Products tab shows Warehouse/POS/Total as 3 fixed columns today. With N
  branches this either becomes N+2 columns (unwieldy past ~4-5 branches) or needs a location filter/dropdown
  instead of fixed columns. **Needs a decision from the client on which.**
- `InvoiceController` / `PurchaseController` (the admin "direct sale" screens) currently deduct from
  `Location::pos()`. If an admin processes a direct sale, which branch does it come out of? Either a
  Location dropdown on those screens, or default to the admin's own `location_id` if they have one, with an
  error if they don't and haven't picked one. **Needs a decision from the client.**

## Open questions to raise with Sir before building Phases 4-5

1. Return Item restock destination (Bucket 2 above) — original sale's branch, or the processing branch?
2. Inventory Items multi-branch display — per-branch columns, or a location filter? (Depends partly on how
   many branches are actually expected — 3-4 is very different from 15.)
3. Admin direct-sale screens — dropdown, or default-to-own-branch?
4. **What exactly is a "branch" for this business** — purely a physical stock location (what this plan
   assumes), or does it also need to track something like ownership/franchise info? Flagging since it came
   up in conversation ("sila din kaya owner kapag?") — if branches can have different owners, that's a
   materially different, larger feature (permissions, reporting boundaries, etc.), not just a stock bucket.
   Don't want to build the wrong shape and redo it.
5. What happens to the existing "POS" location row once real branches exist — renamed into the first real
   branch, kept as a generic fallback/"unassigned" bucket, or retired (existing `location_stocks` under it
   transferred out first)?

## Proposed phases (once the open questions above are answered)

- **Phase 1** — Schema: `users.location_id` migration. Build the Locations admin CRUD (index/create/edit),
  matching this app's standard admin-page pattern. Client can add branches through the UI from here on.
- **Phase 2** — Add a Location field to the User create/edit form so staff get assigned to a branch.
- **Phase 3** — Bucket 1 call sites: swap `Location::pos()` for the logged-in user's `location_id`, with a
  clear error/fallback for admin or unassigned accounts hitting POS-side endpoints.
- **Phase 4** — Bucket 2 (Return Item) — once the restock-destination rule is confirmed.
- **Phase 5** — Bucket 3 (Inventory Items multi-branch display, admin direct-sale branch selection) — once
  the UX decisions are confirmed.

Each phase gets built, live-tested, and checked in on separately — not one large diff — matching how the
rest of this project's larger features have been built.
