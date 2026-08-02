# Activity Log — Schema & Logging Mechanism Notes

**Status: Phase 1 complete (2026-08-02).** This is the reference for every later phase — call the
mechanism the way this file describes, don't invent a parallel pattern. See `PROGRESS.md` for phase
status and the full module inventory/checklist.

## Final schema

`database/migrations/2026_08_02_015443_add_module_source_loggable_metadata_to_activity_logs_table.php`
(builds on the original `2026_03_19_104153_create_activity_logs_table.php`).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `user_id` | FK → `users.id`, nullable | **`nullOnDelete()`**, changed from the original `cascadeOnDelete()` — an audit log must survive the referenced user account being deleted later. Nullable to support system/automated actions with no human actor. |
| `module` | string, nullable | Which feature area, e.g. `Customer`, `DeliveryReceipt`, `Auth`, `POS`. Controlled vocabulary — use the exact module names listed in `PROGRESS.md`'s inventory tables, don't invent new spellings per call site. |
| `source` | string, default `'admin'` | `ActivityLog::SOURCE_ADMIN` / `SOURCE_POS` / `SOURCE_SYSTEM` — which system surface the action came from. See "Choosing `source`" below. |
| `action` | string | e.g. `created`, `updated`, `deleted`, `disposed`, `transferred`, `login`, `login_failed`, `logout`, `forced_logout`, `sale_completed`, `suspended`, `reactivated`, `password_reset_requested`, `password_reset_completed`. Keep to simple past-tense/event verbs; module + action together should read naturally in the viewer (e.g. "Customer · created"). |
| `description` | text, nullable | Human-readable one-liner, e.g. `"Created customer Green Cross Pharmacy"`. Should be readable on its own without needing to join to the `loggable` record. |
| `loggable_type` / `loggable_id` | polymorphic, nullable | The affected record (e.g. the `Customer` row). Null for actions with no single record (e.g. login). |
| `metadata` | json, nullable | Structured context — typically `['before' => [...], 'after' => [...]]` on updates. **Never put passwords/tokens/secrets in here** — `ActivityLog::record()` auto-redacts common sensitive key names as a safety net, but don't rely on that; just don't pass them. |
| `ip_address` | string, nullable | Auto-resolved from the request in `record()` — callers don't need to pass this. |
| `created_at` / `updated_at` | timestamps | Log rows are immutable in practice (nothing updates them after creation) — `updated_at` exists for schema consistency, not because rows are ever edited. |

Indexes added: `(loggable_type, loggable_id)`, `module`, `source` — for the Phase 6 viewer's filters.

## The logging mechanism: `ActivityLog::record()`

Lives in `app/Models/ActivityLog.php`. This is a static method on the model itself — **not** a
separate `ActivityLogService` class — because that already was this codebase's own established
convention before this refactor (the pre-existing `logActivity()`/`logLogin()`/`logLogout()` are
the exact same shape). Introducing a new injected-Service pattern just for this would mean every
one of the 20+ controllers touched across Phases 2–5 needs new constructor DI for something that's
fundamentally "write one row with sensible defaults resolved" — not enough complexity to justify
that ceremony. The redaction safety-net logic lives in a private method on the same class, so the
whole mechanism is in one file.

### How to call it from a controller

```php
use App\Models\ActivityLog;

// After the action completes — e.g. at the end of CustomerController::store()
ActivityLog::record(
    module: 'Customer',
    action: 'created',
    loggable: $customer,                                  // the model just created/updated/deleted
    description: "Created customer {$customer->customer_name}",
);
```

Full signature:

```php
ActivityLog::record(
    string $module,
    string $action,
    ?Model $loggable = null,
    ?string $description = null,
    ?array $metadata = null,
    string $source = ActivityLog::SOURCE_ADMIN,           // or SOURCE_POS / SOURCE_SYSTEM
    ?int $userId = null,                                   // defaults to Auth::id()
): ActivityLog
```

`user_id` and `ip_address` are auto-resolved (current authenticated user, current request IP) —
don't pass them unless logging on behalf of a different user than the one making the request (there
is no known case for this yet).

### Update actions — capturing before/after

For `updated` actions, pass a `metadata` array shaped like:

```php
ActivityLog::record(
    module: 'Customer',
    action: 'updated',
    loggable: $customer,
    description: "Updated customer {$customer->customer_name}",
    metadata: [
        'before' => $customer->getOriginal(),   // or a hand-picked subset of changed fields
        'after' => $customer->getChanges(),
    ],
);
```

Call this **after** `$customer->update(...)` but the `getOriginal()`/`getChanges()` snapshot must
be captured correctly relative to when the save happened — check each controller's exact flow in
its own phase; don't assume `getOriginal()` is still available after the model's already been
re-fetched elsewhere in the same request.

### Choosing `source`

`source` reflects **where the action was performed from**, not which stock location or entity it
affects. Concretely:

- `ActivityLog::SOURCE_ADMIN` (default) — anything reached through the admin backend UI, including
  the admin "direct sale" Purchases screen (`admin/purchases`) even though that screen deducts POS
  stock location-wise — it's still an admin-backend-initiated action.
- `ActivityLog::SOURCE_POS` — only the actual POS terminal screen/flow (`pages/pos.blade.php` and
  its `Api\PurchaseController`/`Api\ItemController` checkout call sites).
- `ActivityLog::SOURCE_SYSTEM` — reserved for any future automated/non-request-triggered action (a
  scheduled job, etc.) — no current call site needs this, but it's defined so Phase 3+ doesn't have
  to invent a fourth ad-hoc value if something like that comes up.

Role alone (`admin` vs `user`) is **not** a reliable signal for this — both roles can reach `/pos`
(the route isn't admin-restricted) — so `source` must be set explicitly per call site based on
which controller/route is actually running, not inferred from `auth()->user()->role`.

### Backward compatibility (resolved in Phase 5 — this section is now historical)

`logActivity()`, `logLogin()`, `logLogout()` existed for backward compatibility through Phase 1-4.
**Phase 5 migrated both call sites in `Auth\AuthenticatedSessionController` to `record()` directly**
(needed to also support `login_failed`, which these helpers couldn't express) **and removed all three
methods from `ActivityLog`** once nothing called them anymore — confirmed via a repo-wide grep before
deleting. If you're reading this in a future phase and see a reference to these method names, they no
longer exist; use `record()`.

### What NOT to log (per user-confirmed decisions, 2026-08-02 — see `PROGRESS.md`)

- View-only actions (opening a report, a `show()`/detail page) — **excluded** from this refactor.
  Only create/update/delete/state-change actions get logged.
- Individual barcode scan lookups at POS — **not logged**. Only the resulting sale/checkout is
  (under `module = 'POS'`, `source = SOURCE_POS`).

### Verified (Phase 1, 2026-08-02)

- Migration ran clean; all 5 new columns + 3 indexes present.
- `ActivityLog::record()` tested live: correctly resolves `user_id`/`ip_address`, writes
  `loggable_type`/`loggable_id` such that the `loggable()` MorphTo relation resolves back to the
  real model, and **auto-redacts a `password` key passed into `metadata`** (confirmed the stored
  JSON shows `"password":"[REDACTED]"` instead of the real value).
- Confirmed the `user_id` foreign key's `DELETE_RULE` is now `SET NULL` (was `CASCADE`) via
  `information_schema.REFERENTIAL_CONSTRAINTS`.
- Backward-compat `logLogin()` confirmed still working post-migration, now stamping
  `module = 'Auth'` automatically.
- All test rows created during verification were deleted afterward — no leftover test data.
