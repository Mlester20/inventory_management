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

Verified live (2026-08-19): temp `admin_staff` account confirmed against every item above (masked cost, no
delete option + server-side block, no supplier/customer create-edit UI + server-side block except the DR
JSON path, which was confirmed to still succeed), a temp `admin` account confirmed unaffected (still creates
suppliers, sees real cost), and `php artisan test` showed the same 33 pre-existing unrelated failures (no
regressions). Test accounts cleaned up, admin password restored after.

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
