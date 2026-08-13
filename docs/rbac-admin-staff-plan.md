# Admin / Admin Staff Role Split — Implementation Plan

**Status: planned, not yet built — blocked on the same open question as before (which specific peso figures
get hidden) before any of this can be built.**

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
