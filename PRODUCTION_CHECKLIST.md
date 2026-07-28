---

## 🚀 Production Readiness Checklist

Things to verify before cutting a `production` branch from `main`. Grouped by
what's actually load-bearing vs. standard housekeeping.

---

### 1. Branch Merge Order

The recent feature branches are stacked, not independent — each was branched
off the previous one, not off `main`:

```
main
 └─ feature/item-search
     └─ feature/login-design
         └─ feature/category-crud
             └─ feature/forgot-password   (current)
```

- [ ] Merge in this exact order (`item-search` → `login-design` →
      `category-crud` → `forgot-password` → `main`) to avoid conflicts or
      dropped work.

---

### 2. Resend / Email — ⚠️ Blocking

- [ ] Verify a real domain in the Resend dashboard (resend.com/domains).
      **Sandbox mode (`onboarding@resend.dev`) can only deliver to the
      account's own signup email** — real users will get zero
      password-reset emails until this is done.
- [ ] Update `MAIL_FROM_ADDRESS` to an address on the verified domain
      (e.g. `no-reply@yourdomain.com`) — config-only change, no code changes
      needed.
- [ ] Send a real test reset-code email to a non-owner address once the
      domain is verified, to confirm delivery actually works end to end.

---

### 3. `.env` for Production

`.env` is gitignored, so none of this travels with the branch — it has to be
set up fresh on the production server.

- [ ] `APP_DEBUG=false` (currently `true` locally — leaking this in
      production exposes stack traces and secrets on every error page)
- [ ] `APP_ENV=production`
- [ ] Real `APP_URL`
- [ ] Real DB credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- [ ] `RESEND_API_KEY` set as a real production secret (note: the package
      reads `RESEND_API_KEY`, not `RESEND_KEY` — easy to typo)
- [ ] `APP_NAME` set deliberately before going live — changing it later
      invalidates every active session (it's baked into the session cookie
      name), so treat renames as a low-traffic-window operation, not a
      casual one.

---

### 4. Seeders — Don't Ship Demo Data

`DatabaseSeeder` creates fake data: 10 random users, a known
`admin@gmail.com` / `admin123` account, sample pharmacy products, and a
"Walk-in Customer".

- [ ] Decide: production deploy should be `php artisan migrate --force`
      only (no `--seed`), or a separate real production seeder — not the
      demo `DatabaseSeeder` as-is.
- [ ] If any admin account needs to exist on day one, create it manually
      with a real password, not the seeded `admin123`.

---

### 5. Dependencies

- [ ] `composer install --no-dev --optimize-autoloader` for the production
      build (composer.json currently pulls in dev-only packages like
      `fakerphp/faker`).

---

### 6. Regression Pass

One clean end-to-end walkthrough covering everything from the recent
branches *together*, not just individually — and across both `admin` and
non-admin (`user`) roles, since several pages render a different shared
layout per role:

- [ ] Login → Forgot Password → email code → Reset Password → re-login
- [ ] Category CRUD (create / edit / delete, including the
      still-referenced-so-blocked delete case)
- [ ] Searchable item/generic-name pickers on: Sales Order, Sales Quote,
      Delivery Receipt, Purchase Order, Goods Receipt, Invoice, Inventory
      Adjustment
- [ ] Sales Order and Delivery Receipt print output
- [ ] No leftover test data in the database (test categories, temp users,
      stray stock movements) from development/QA sessions

---

### 7. Caching

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

Run all three and confirm no errors — untested so far in this project.

---

### 8. Secrets Hygiene

- [ ] Confirm `.env` was never committed (`git ls-files .env` should return
      nothing)
- [ ] Confirm `.env.example` only has placeholder values, no real API keys
