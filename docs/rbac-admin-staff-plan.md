# Admin / Admin Staff Role Split — Implementation Plan

**Status: Phase 1 built (2026-08-19, branch `feature/rbac-admin-staff`).** The role infrastructure (middleware,
validation, dropdown, layout-picker/UI parity) and Sir's first concrete restriction list are live. Still open:
the original "which peso figures get hidden system-wide" question (Total Sales, Reports, etc.) — Sir has only
given the list below so far; more may follow.

## Phase 1 — what's actually built

- **Role infra**: `AdminOnly` middleware, login-redirect logic (both `routes/web.php` and
  `AuthenticatedSessionController`), and every `@extends(...)` layout-picker / admin-only UI-visibility spot
  (~19 total) now treat `admin` and `admin_staff` identically — same sidebar, same pages open, same
  Invoice/Sales Order/Sales Quote delete actions, same Delivery Receipt quick-add-customer button. Two
  deliberate exceptions: the "Add customer here" hint link in Sales Quote/Sales Order create (would point
  admin_staff at a page where they can't actually add one) and the admin-only account-protection checks in
  `UserController` (suspend/delete) — see decisions below.
- **Role validation**: `UserController::store` now allows `admin_staff` as a role value; the Add User modal
  has an "Admin Staff" option.
- **Decided (2026-08-19)**: admin_staff accounts get **no** suspend/delete protection — a full admin can
  freely suspend or delete one, same as a plain `user` account. Only `admin` keeps that protection.
  `UserController.php:57,83` left unchanged (already scoped to `'admin'` only).

### Sir's first restriction list (all built, server-side enforced + UI-hidden)

1. **Products/Inventory — Hide Cost.** The Products tab's Cost column shows `••••` for admin_staff; the New/
   Edit Item modal's Cost field is omitted entirely (not just hidden — never rendered in the DOM, including
   the `data-unit-cost` attribute that used to feed the edit modal). Scoped to the Inventory Items module
   only — Purchase Order/Goods Receipt/Purchase Invoice still show cost, since that's operationally required
   to receive stock.
2. **Products/Inventory — Disable Item Deletion.** Delete option hidden from the Products tab's row actions
   for admin_staff, and `ProductController::destroy` rejects admin_staff server-side even if hit directly.
3. **Disable Customer and Supplier Creation and Editing.** Supplier: `SupplierController::store`/`update`
   both reject admin_staff, "New Supplier"/"Edit" hidden from `admin/supplier.blade.php`. Customer: same for
   `update`, but `store` has a carve-out — **decided (2026-08-19)**: Delivery Receipt's inline "quick add new
   customer" popup (`admin/delivery-receipts/create.blade.php`) must keep working for admin_staff, since
   blocking it would break normal DR/delivery workflow, not just an admin settings screen. The dedicated
   Customers page's "New Customer" flow is a traditional form POST; the DR quick-add is a `fetch()` call with
   `Accept: application/json`. `CustomerController::store` uses that existing `$request->expectsJson()`
   signal to tell the two apart — admin_staff blocked on the form path, allowed on the JSON path. "New
   Customer"/"Edit" hidden from `admin/customers.blade.php` either way.

**Correction (2026-08-19):** Deletion of customers/suppliers was initially left open to admin_staff (not
explicitly in Sir's "Creation and Editing" list) — user flagged this as wrong after testing live ("bakit
nabubura ko yung customers? naka session admin_staff, dapat naka disabled yung delete button"). Deletion is
now blocked for admin_staff the same way creation/editing is: `CustomerController::destroy` and
`SupplierController::destroy` both reject admin_staff server-side, and the Delete option is hidden from both
`admin/customers.blade.php` and `admin/supplier.blade.php`.

**Extension (2026-08-19):** User Management CRUD is now admin-only too — user's own suggestion after
discussing what else should be concealed, confirmed with "oo, if the session is 'admin_staff' disable mo crud
operations for the user management." `UserController::store`/`toggleStatus`/`destroy` all reject admin_staff
server-side; the "Add User" button and the entire per-row Actions dropdown (Suspend/Reactivate, Delete) are
hidden from `admin/users.blade.php` for admin_staff — but the Users list itself stays visible (read-only), so
admin_staff can still see who has access to the system, just can't change it.

**Extension (2026-08-19): Dashboard financial figures masked.** This closes the original open question from
the top of this doc — Total Sales, Total Purchases, Inventory Value, and POS Revenue stat cards, plus the
hero "Sales vs. POS Revenue vs. Expenses" trend chart and the "Last 5 Days" chart, are all masked for
admin_staff (either `₱••••` or a "restricted to full admin accounts" placeholder in place of the chart).
Deliberately scoped to *aggregate* figures only, not per-record ones — Recent Invoices' Amount column, Recent
POS Sales' Unit Price/Total columns, and Recent Activity's Amount column are all left visible, since those are
operational (needed to actually help a customer or verify a transaction), matching the same "don't cripple
day-to-day work" reasoning already used for Cost on Purchase Order/Goods Receipt. The two charts' underlying
JSON data (`$salesTrendByPeriod`, `$last5DaysComparison`) is never embedded in the page source for
admin_staff — the entire chart-init `<script>` block is gated server-side in Blade, not just visually hidden,
same true-concealment approach used for Cost's `data-unit-cost` attribute. Reports (Sales Summary, COGS,
Expense Summary) are a separate surface, not yet addressed — worth deciding later.

Also flagged as worth deciding later, not yet built: whether a proper Trash/Recycle Bin (SoftDeletes + restore
UI) is worth building for Customer/Supplier/Product given the delete-snapshot-in-audit-log approach already
covers the actual recoverability risk cheaper.

**Decided (2026-08-29): Trash/Recycle Bin built for GenericName, Product, SalesOrder, DeliveryReceipt,
Invoice** — Sir's actual ask ("INV Item", SO, DR, SI), not Customer/Supplier (those keep the existing
delete-snapshot-in-audit-log approach, unchanged). All 5 now use Laravel's `SoftDeletes` — `delete()` sets
`deleted_at` instead of removing the row, restorable via a new `restore()` action on each controller. Same
"per-module `show_trashed` toggle" UI shape as the existing `show_zero` toggle on the Lot/Serial tab, not a
separate unified Trash page. V1 is restore-only — no "Permanently Delete" action yet.

Alongside this, closed an existing gap: `admin_staff` previously had **no role check at all** on
`SalesOrderController`/`DeliveryReceiptController`/`InvoiceController`/`GenericNameController::destroy()` —
only business-state guards (delivered_qty, draft-only, sales-exist). Some Blade delete buttons were even
gated to `admin`+`admin_staff` together, and the Sales Order/Delivery Receipt show-page "Delete Draft"
buttons had no gate at all. All 5 `destroy()`/new `restore()` methods now reject `admin_staff` server-side
(same pattern as `ProductController`), and every Delete/Restore button across
`inventory-items/index.blade.php` (General Item + Products tabs), `sales-orders/index.blade.php`+`show.blade.php`,
`delivery-receipts/index.blade.php`+`show.blade.php`, and `invoices/index.blade.php` is now `admin`-only in
the Blade too — closing the gap Sir wanted ("admin_staff role restricted totally yung pag bura nya sa ganong
data").

Separately fixed this same session: `GenericNameController::destroy()` had no FK-error handling at all —
deleting a generic item referenced by any Sales Order/Sales Quote line threw a raw SQLSTATE 1451 500 error
(reproduced live). Now soft-deleted, so this no longer applies in the normal flow, but the graceful
`QueryException` catch was kept for defense-in-depth.

Verified live (2026-08-19): temp `admin_staff` account confirmed against every item above (masked cost, no
delete option + server-side block, no supplier/customer create-edit UI + server-side block except the DR
JSON path, which was confirmed to still succeed), a temp `admin` account confirmed unaffected (still creates
suppliers, sees real cost), and `php artisan test` showed the same 33 pre-existing unrelated failures (no
regressions). Test accounts cleaned up, admin password restored after.

**Update (2026-10-17): Sir sent a full access matrix** (`docs/proposals/USER_ADMIN STAFF ACCES.pdf`) covering every module, superseding ad-hoc decisions above where they conflict. Two explicit **reversals** of earlier decisions: (1) Users page — previously decided "stays visible, read-only" (2026-08-19 entry above); now fully hidden/blocked for admin_staff, same as `admin`-only pages. (2) Audit Trail — previously scoped to "own actions only"; now fully hidden/blocked, no own-actions view at all. Both `UserController::index()` and `ActivityLogController::index()` now redirect admin_staff to the dashboard with an Alert, and both menu items are wrapped in `role === 'admin'` only (not `admin`+`admin_staff`).

Also newly restricted this round (none of these existed before): New Generic Item button, New Item + Clone buttons (Products), Inventory Adjustment "Save" (finalize — Save Draft still open), Stock Disposal "Save" (no draft concept here), Expiration Report "Dispose" button (flows through Stock Disposal), Sales Quote "Save Sales Quote" (finalize), Stock Transfer Delete-Draft + "Save Stock Transfer" (finalize), Repack "Save Repack" (finalize — no destroy() exists for Repack, nothing to restrict there), Goods Receipt "Delete Draft", Purchase Invoice "Save Purchase Invoice" (finalize), Expenses "Delete". Also newly HIDDEN entirely (page + menu, admin-only via redirect pattern matching CogsController): Taxes, POS Sales/Purchases (the legacy admin `purchases` module, not Purchase Order), Sales Report, Purchase Report, Expense Report.

**Resolved (2026-10-17, later):** the Inventory Summary conflict is fixed — the PDF's "No changes" for BOTH User and Admin Staff on that report meant the existing 2026-08-19 masking (Unit Price/Total Value columns, grand-total row) needed to come OFF entirely, not stay. Removed all three `role === 'admin'` gates in `reports/inventory-summary.blade.php`; the controller never computed this data conditionally, so no backend change was needed. Verified live: admin_staff now sees Unit Price, Total Value, and the grand-total row.

**Built (2026-10-17, later):** Return Items "Save Draft" — the one PDF item that had no draft concept to restrict. `return_items` gets an `is_draft` boolean (status stays a separate concern, same split as Purchase Order) plus `quantity`/`return_date`/`reason` made nullable; `store()`/`update()` split draft vs posted exactly like every other module here, and the previously-dead `edit()`/`update()` resource routes now drive Continue Editing/resume-and-finalize. Also closed a safety gap this surfaced: a draft's `status` still defaults to `pending`, so `approve()`/`reject()` needed an explicit `isDraft()` guard or a draft could be approved before ever being finalized. Verified live with temp accounts: admin_staff blocked from finalizing (UI + server-side bypass attempt both confirmed blocked via ActivityLog trail), full admin can finalize a resumed draft, Delete Draft works.

Both of Sir's PDF items are now fully closed — nothing outstanding from `docs/proposals/USER_ADMIN STAFF ACCES.pdf`'s ADMIN STAFF column remains unbuilt.

**Update (2026-10-17, later): the PDF's USER INTERFACE column analyzed too.** Found a much bigger gap than admin_staff ever had — a plain `user` account had ZERO restriction on Sales Quote, Sales Order, Delivery Receipt, Invoice, or Customers (every finalize-restriction check built this session only ever tested `role === 'admin_staff'`). Also found a real blocker: several rows (General Item View, Products View, Lot/Serial & Expiry, Categories, Inventory Adjustments, Stock Disposals, Suppliers, Customer Type, Goods Receipt, Expenses, Expiration Report, Inventory Summary, Product History) say "Disable [button]" for User — implying they should be *viewable* — but `user` currently gets a flat 403 on all of them via the `AdminOnly` middleware. Opening those up is a bigger architectural change, not a simple restriction, so a question PDF was sent to Sir first: `docs/proposals/USER-ACCESS-QUESTION.pdf`.

Per the user's direction ("gawin mo ang available na i-restrict"), built everything that didn't depend on that open question, same branch (`f3f568d`):
- Sales Quote / Sales Order / Delivery Receipt: broadened the finalize check from `=== 'admin_staff'` to `!== 'admin'` (Save Draft stays open to everyone).
- Sales Order Summary: fully blocked for `user` (all 8 controller methods) — matches "HIDE".
- Sales Invoice: fully blocked for `user` on every method they could reach (index/create/store/show/edit/update/archive/unarchive) — matches "HIDE". destroy/restore/cancel were already admin-only via route middleware.
- Customers: broadened New/Edit from `admin_staff`-only to `!== 'admin'`, preserving the Delivery Receipt quick-add JSON carve-out for `user` too (verified live it still works — breaking it would stop normal DR creation).
- `layout/user.blade.php`: removed the now-blocked Sales Invoice and Sales Order Summary menu items.

Verified live (temp `user`/`admin_staff`/`admin` accounts): menu absence, redirects, disabled buttons, a direct-POST bypass on Sales Order finalize (blocked, nothing created in DB), DR quick-add still works for `user`, admin_staff and full admin both unaffected by the broadened checks.

Still waiting on Sir's answer before touching: whether to open view-access to the AdminOnly-gated modules for `user`, and whether "user" in his spec even means the current POS/regular staff role at all.

Branch: `feature/admin-staff-full-restrictions`. Verified live (2026-10-17): temp `admin_staff` + temp `admin` accounts — menu absence, page-redirect, button-disabled states, Draft-save-still-works, and a direct-POST bypass (Stock Transfer finalize) all confirmed server-side, not just UI. Temp accounts cleaned up after.

**Naming reversed (2026-08-13):** the earlier direction in this doc (rename full-access `admin` →
`super_admin`, free up `admin` for the restricted tier) was reverted. Steady na lang sa `admin` bilang full
control — walang pagbabago dito, parehas pa rin ng ngayon. The new restricted tier gets its own new name
instead: **`admin_staff`**. A first pass at this under the `super_admin` naming was actually built and
verified working (Phase 1, live-tested with temp accounts), then fully reverted — migration rolled back and
deleted, all code changes reverted via `git checkout` — once this naming decision came in, since keeping
`admin` unchanged is simpler than a rename anyway (see below).

## Context

Today the system has exactly 2 roles: `user` (POS/staff) and `admin` (full access to everything). Sir wants
to add a new, more restricted tier. Confirmed direction: `admin` keeps meaning exactly what it means today —
full control, no rename, no change to any existing account. The new tier is a separate role, **`admin_staff`**.

Also relayed by the user (2026-08-13, still being double-checked, not yet final): **the restriction is
specific peso figures, not whole pages/modules.** Example given: Total Sales should be hidden from
`admin_staff`, visible only to `admin`. See "Open question" below — this is the same open question as
before, just re-pointed at the new role name.

## Good news: even simpler now than the super_admin version

Since `admin` isn't being renamed, there is **no data migration and no rename of any existing account** —
today's single `admin` account stays exactly as-is, no revert risk, no downtime. The only schema-adjacent
work is allowing `admin_staff` as a new value for the plain `string` `users.role` column (zero schema
migration needed for that either) and adding it to the create-user validation/dropdown.

## What actually needs to change, once the open question is resolved

**PHP:**
| File | What it does | Needs to become |
|---|---|---|
| `UserController.php:29` role validation | Currently just `'required'` | Add `in:user,admin,admin_staff` |
| `admin/users.blade.php` role `<select>` | Currently `user`/`admin` only | Add an `admin_staff` option |
| `AdminOnly` middleware | Gates the whole admin backend | Becomes `in_array($role, ['admin', 'admin_staff'])` — `admin_staff` still opens the same pages, since the restriction is which *numbers* render, not which *pages* load |
| `UserController.php:57,83` account-protection ("admin accounts can't be suspended/deleted") | Currently `$user->role === 'admin'` | **Open question**: does this protection extend to `admin_staff` accounts too, or is `admin_staff` a normal manageable account (since it's not the "can't leave the system without an admin" case `admin` protection exists for)? Leaning toward: only `admin` needs this protection, `admin_staff` accounts can be freely suspended/deleted by an `admin` — but confirm before building |

**Blade** — same ~19 mechanical layout-picker / inline-toggle spots audited before (Invoices, Sales Orders,
Sales Quotes, Delivery Receipts, Customers `@extends(...)` + a few inline `@if` admin-only buttons, plus the
profile badge). All of these are "which sidebar/theme to show," not a security boundary — treat `admin` and
`admin_staff` identically here too, same reasoning as before, just swap which role name is the "new" one:
`in_array(Auth::user()->role, ['admin', 'admin_staff'], true)`.

## Mechanism for the actual restriction (value-masking, not route-blocking)

No permissions/policy system exists today — access control is flat role checks. Given the "specific peso
figures" direction, the mechanism is **not** a middleware blocking a route — `admin_staff` still needs to
*open* the Dashboard/Reports pages, just not see certain numbers on them. Closer to a Blade-level check
wrapping specific figures:

```blade
@if(auth()->user()->role === 'admin')
    {{ number_format($totalSales, 2) }}
@else
    ••••••
@endif
```

or a small Blade directive/helper if the same pattern repeats enough times to be worth it — decide once the
actual list of figures is known, don't build the abstraction speculatively.

## Open question — the one that actually blocks everything here

**What exactly should `admin_staff` NOT be able to see?** Relayed so far: "digits" — example given, Total
Sales. Still ambiguous at two very different scales, needs an explicit confirm from Sir, not an assumption:
- **Narrow reading**: just aggregate/summary figures — Dashboard's Total Sales card, maybe the Reports
  section (Sales Summary, COGS, Expense Summary).
- **Broad reading**: every peso amount system-wide — Invoice totals, DR amounts, Customer
  balances/receivables, Supplier payables/GRNI, etc. This would be much bigger and would arguably cripple
  `admin_staff`'s ability to do normal transaction work (processing an Invoice without seeing its amount
  doesn't make sense), so this reading seems unlikely to be what Sir means — but needs an explicit confirm.

Once scoped, still need the actual list of which screens/figures qualify — same "give me the list, don't
assume" approach as before. Also still open: the account-protection question above (does `admin_staff` get
the same "can't be suspended/deleted" protection as `admin`?).

## Verification plan (once unblocked)

1. `php -l` on every changed file.
2. Create a temp `admin_staff` account, confirm it can open every page an `admin` can (no page-level 403s),
   but the confirmed list of peso figures render masked/hidden while everything else renders normally.
3. Confirm a temp `admin` account still sees everything unmasked, exactly as today.
4. Confirm a plain `user` account is still fully blocked as before (unrelated to this change, but a quick
   regression check).
5. Clean up all test accounts, verify deletion via explicit existence checks afterward.

## Addendum (2026-10-18) — pos/user role split, branch `feature/pos-role-split`

Sir confirmed (screenshot reply "Yes") a role-architecture change on top of everything above: the plain
`user` role was always meant to be the POS/cashier account, so it's being renamed/migrated to a new `pos`
role, and `user` is repurposed into a **third, narrower admin-side tier** matching the USER column of
`docs/proposals/USER_ADMIN STAFF ACCES.pdf` — narrower than `admin_staff`, not a POS account at all.

**Phase 1 (commit `9fb824b`)**: data migration moved every existing `role = 'user'` account to `role =
'pos'`; `blockPosRole()`-style guards (Invoice, Sales Order Summary) renamed and re-pointed at `'pos'`;
`UserController` role dropdown now offers `pos` / `user` / `admin` / `admin_staff`.

**Phase 2 (commit `a1dca7b`)**: gave the repurposed `user` tier its actual restricted admin-side view —

- Most existing `admin_staff`-only guards (controllers, Blade `@disabled` checks, the 21 layout-picker
  `@extends(...)` views, the `AdminOnly` middleware) were broadened from `=== 'admin_staff'` /
  `in_array([..., 'admin_staff'])` to also cover `'user'`, since on most modules the PDF gives `user` the
  same "view with disabled buttons" treatment as `admin_staff`.
- Four modules are **divergent** — `user` is strictly more restricted than `admin_staff` there — so each got
  its own `blockUserTier()` guard (blocks only `'user'`, `admin_staff` unaffected) instead of a broadened
  check: Purchase Orders, Purchase Order Summary, Purchase Invoices, Stock Transfers, Repacks. The Dashboard
  got the same treatment (hidden from `user`, untouched for `admin_staff`).
- Two modules are the **inverse** divergence — `admin_staff` keeps full, unrestricted access while `user`
  is fully hidden — per the PDF's literal "No changes" vs "HIDE" rows: Sales Order Summary and Sales
  Invoice. Confirmed before building rather than assuming uniform `admin_staff ⊇ user` ordering.
- Sidebar menu (`layout/app.blade.php`): Purchase Orders/Purchase Order Summary/Purchase Invoices sub-items
  and the standalone Stock Transfer/Repack items are wrapped in `@unless(Auth::user()->role === 'user')`,
  while Goods Receipts (inside the same shared "Purchase Order" parent menu) stays unconditional/visible to
  `user`. `admin_staff`'s sidebar is untouched.
- Post-login/root redirect (`AuthenticatedSessionController`, `routes/web.php`) extended to a 3-way split:
  `admin`/`admin_staff` → `admin.dashboard`, `user` → `inventory-items.index`, `pos` → `pages.home`.

**Verified live (temp QA accounts, one per role, all cleaned up afterward, including the stray ActivityLog
login rows the test runs generated)**: full allowed/blocked route matrix for `user`, `admin_staff`, `admin`,
and `pos` (regression); sidebar HTML presence/absence for the divergent menu items; a direct raw-POST bypass
attempt on Stock Transfer as `user` (blocked server-side, zero `StockTransfer` row and zero new
`ActivityLog` entry created). `admin_staff` and `admin` behave identically to before this phase; `pos`
behaves identically to the old plain-`user` role.

Still not done: no follow-up message/PDF to Sir about Phase 2 completion has been sent — not requested yet.
`feature/pos-role-split` has not been pushed to origin — local commits only, per standing instruction.

**Re-verified against the PDF row-by-row (2026-10-18):** cross-checked every USER INTERFACE row in
`docs/proposals/USER_ADMIN STAFF ACCES.pdf` against what's built. All 19 explicit "HIDE" rows for the USER
column (Sales Order Summary, Sales Invoice + New Invoice, POS Sales/Purchases, Purchase Orders + New PO +
PO Summary, Purchase Invoice + New PI, Stock Transfers + New ST, Repacks + New Repack, COGS/Sales/Purchase/
Expense Report, Audit Trail, Import Results, Users, Taxes) match what's hidden in code — the heavy hiding
on the `user` tier is expected per the PDF, not over-restriction. One gap found: **Dashboard is hidden from
`user` in code (`DashboardController`) but is not a row in the PDF at all** — that block was an unrequested
assumption from the earlier session. Asked the user directly; **decision: keep it hidden** (admin-side page,
safer default) even though it's not literally specified by Sir's document — flagging here in case Sir
later asks why `user` can't see the Dashboard.
