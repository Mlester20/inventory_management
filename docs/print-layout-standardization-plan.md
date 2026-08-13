# Print Layout Standardization — All Document Types

**Status: Inventory Adjustment's print built and live (2026-08-13, done ahead of the phase order below,
per direct request) — modeled directly on Invoice's layout but built inline in that file rather than via
the shared partials described below, since Phase A (extracting those partials) hasn't happened yet.
Rest of this plan (Phase A/B and the remaining 5 of Phase C) still not started.**

## Context

Sir wants consistent, professional print output across every document, not just Invoice. Right now:

- **4 of 10 document show pages have a Print button at all**: Sales Order, Invoice, Delivery Receipt,
  Stock Disposal (confirmed via grep for `window.print()` / `@media print` across `resources/views/admin`).
- **Only Invoice's print actually looks like a real document** (`resources/views/admin/invoices/show.blade.php`):
  a proper letterhead (logo + company name/address/proprietor/TIN/email from `config('company.php')`), a
  bordered "INVOICE TO" info box, a details strip table, an items table with a totals side-table, a
  signature block, and a legal disclaimer — all styled with dedicated print CSS (`@page` margins, hidden
  app chrome, page-break-avoid rules, exact-color header printing).
- **Sales Order, Delivery Receipt, and Stock Disposal's "Print" just prints the plain on-screen admin
  view** — a Bootstrap card with a data table, no letterhead, no formal layout. Visually inconsistent
  with Invoice.
- **6 document types have no print at all**: Goods Receipt, Purchase Order, Purchase Invoice, Inventory
  Adjustment, Stock Transfer, Sales Quote.

**Confirmed direction**: Invoice's print layout is the reference design. Every other document's print
should look like it belongs to the same document family — same letterhead, same typography, same base
print rules — while each document's own fields (items table columns, whether there's a money total,
who the "to" box is addressed to) stay specific to that document, since a Purchase Order and an
Inventory Adjustment don't have the same fields as an Invoice.

## Design approach — what's shared vs. what stays per-document

Invoice's print is a good template but is entirely self-contained in one file today (letterhead HTML +
CSS all inline in `invoices/show.blade.php`). Copy-pasting that block into 9 more files would work, but
means fixing a typo or tweaking the letterhead later requires editing 10 files identically — worth
extracting the genuinely identical parts into shared partials before this fans out, unlike this app's
usual per-file-duplicated JS pattern (that JS differs by field names per form; this content is byte-for-byte
identical across documents, which is exactly the case worth sharing).

**New shared partials** (extracted from Invoice's current implementation, Invoice becomes the first
consumer):
- `resources/views/partials/print/letterhead.blade.php` — logo + company block (reads `config('company.*')`,
  unchanged), accepts `$docTitle`, `$docNo`, `$docDate` for the title box on the right.
- `resources/views/partials/print/base.css.blade.php` (included via `@include` inside a `<style>` tag) —
  the reusable print mechanics: `@page` margins, hiding `.no-print`/`#layout-menu`/`#layout-navbar`/
  `.content-footer`, page-break-avoid rules, exact-color printing for header cells. Renamed from
  Invoice's `.invoice-*` class prefix to a generic `.print-*` prefix so it's not Invoice-specific naming
  leaking into shared code.
- `resources/views/partials/print/signature-block.blade.php` — the 3-column "Prepared By / Approved By /
  Received By" row, accepting configurable labels (a Goods Receipt might want "Received By (Warehouse)"
  instead of "Approved By", for example).

**Stays per-document** (each show.blade.php keeps its own):
- The "TO" info box — who it's addressed to varies: customer (Invoice, Delivery Receipt, Sales Order,
  Sales Quote) vs. supplier (Goods Receipt, Purchase Order, Purchase Invoice) vs. no external party at
  all (Inventory Adjustment, Stock Disposal, Stock Transfer — these are internal movement records, not
  documents sent to someone).
- The details strip table — Invoice's has Payment Term/Due Date/OSCA-PWD, which doesn't apply to e.g.
  Purchase Order (Supplier/Order Date/Status instead). Each document defines its own relevant columns.
  Styled with the same shared CSS classes for visual consistency, not a shared HTML structure.
  - The items table — column set genuinely differs per document (Invoice needs Unit Price/Discount/VAT/
  Amount; Goods Receipt needs Batch/Expiry/Unit Cost with no VAT; Inventory Adjustment needs
  Location/Batch/Qty/Remarks with no pricing at all). Same shared table CSS classes, per-document columns.
- The totals side-table — only documents with money get one (Invoice definitely; Purchase Order and
  Goods Receipt reasonably could show a grand total; Delivery Receipt, Inventory Adjustment, Stock
  Disposal, Stock Transfer are quantity-movement records with no money total — skip it for those).
- The legal disclaimer — Invoice-specific text about transshipment; only applies to Invoice, not
  reused elsewhere.

## Phases

- **Phase A — extract the shared partials, Invoice becomes the reference implementation.** Pull the
  letterhead, base print CSS, and signature block out of `invoices/show.blade.php` into the 3 new
  partials above, then update Invoice itself to `@include`/`@component` them instead of inline HTML/CSS.
  Verify Invoice's printed output is pixel-identical to today's before touching anything else — this
  phase is a pure refactor with a strict "no visual change" bar, since Invoice's design is already
  correct and is the thing everything else will be judged against.
- **Phase B — retrofit the 3 existing-but-plain prints** to the letterhead design: Sales Order,
  Delivery Receipt, Stock Disposal. These already have a Print button and `@media print` rules, so this
  is "replace the plain card layout with the new letterhead + doc-specific TO box + items table," not
  "add print from scratch." Sales Order and Delivery Receipt get a "DELIVER TO"/customer box; Stock
  Disposal has no external party, so it gets the letterhead + doc-title box but no TO box, similar to
  where Inventory Adjustment and Stock Transfer will land in Phase C.
- **Phase C — build print from scratch for the 6 missing document types**, roughly in order of how
  close their shape is to Invoice (closest first, so early ones validate the shared partials against a
  supplier-facing money document before tackling the internal, no-money ones):
  1. Purchase Order — supplier-facing, has qty/unit cost/amount, closest shape to Invoice.
  2. Goods Receipt — supplier-facing, has qty/unit cost/batch/expiry, no VAT.
  3. Sales Quote — customer-facing, same shape as Sales Order (no delivery-specific fields).
  4. Purchase Invoice — supplier-facing, billing document, closest second cousin to Invoice.
  5. Inventory Adjustment — internal, no external party, no money total. (Its Edit/Delete buttons are a
     separate, still-open question per `docs/inventory-adjustment-edit-delete-print.md` — Print itself
     isn't blocked by that and can ship independently, as already agreed.)
  6. Stock Transfer — internal, no external party, no money total, same shape as Inventory Adjustment.

## Verification

1. `php -l` on every changed/new file.
2. For Phase A: print-preview (or PDF-export) Invoice before and after, confirm no visual regression.
3. For each document in Phase B/C: print-preview and confirm the letterhead, TO box (where applicable),
   items table, totals (where applicable), and signature block all render correctly with real data, and
   that `.no-print` elements (buttons, sidebar, nav) are hidden in the print view.
4. Confirm existing non-print functionality on each touched show page (e.g. Delivery Receipt's
   "Create Invoice" checkbox flow, Sales Order's related-documents list) still works — this is a visual
   layout change, not a logic change, so nothing functional should regress.
