# SAIMS-CORRECTIONS-2.0.pdf — Final Status

**All 7 items complete (2026-08-14).**

| # | Item | Status |
|---|------|--------|
| 1 | Item Creation — duplicate code notification | ✅ Done |
| 2 | Inventory Adjustment — full item description text | ✅ Done |
| 3 | Inventory Adjustment — Lot/Batch dropdown (active batches) | ✅ Done |
| 4 | Inventory Adjustment — Edit/Delete/Print action buttons | ✅ Done (Write-off replaces Delete — see `docs/inventory-adjustment-edit-delete-print.md`) |
| 5 | Sort/arrange item list (applies to other forms) | ✅ Done — 8 forms |
| 6a | Purchase Order — Unit + Remarks fields | ✅ Done |
| 6b | Purchase Order — Generic Item selection (not specific brand) | ✅ Done — schema change, brand now chosen at Goods Receipt time |
| 6c | Purchase Order — Generate Line button | ✅ Done |
| 7a | Goods Receipt — Split Item button | ✅ Done |
| 7b | Goods Receipt — Batch No. dropdown (active batches) | ✅ Done |
| 7c | Goods Receipt — Allow partial/incomplete recording | ✅ Done |

## Also built along the way (not in the original PDF, added per client requests during this work)

- **Save Draft** for Inventory Adjustment — status field (draft/posted), safe editable/deletable drafts,
  stock only moves at finalize. Built as the pattern-setter; not yet rolled out to other modules.
- **Direct Batch No. / Expiration Date edit** on the Lot/Serial & Expiry tab — pure metadata fix for typos,
  no stock impact.
- **Print layout standardization** started (Invoice's letterhead design as the template) — see
  `docs/print-layout-standardization-plan.md` for the rest of the rollout (Sales Order, Delivery Receipt,
  Stock Disposal need retrofitting; Goods Receipt, Purchase Order, Purchase Invoice, Stock Transfer,
  Sales Quote still need it built from scratch).

## Notable fix caught during Phase C verification

Phase C (PO's Generic Item picker) initially introduced a regression: `Api\PurchaseOrderController::
pendingItems()` switched to reading `generic_name_id` directly off `purchase_order_items`, but that
column is null on every pre-existing ("legacy") PO line created before this migration — which would have
silently broken the already-shipped Goods Receipt "Brand" sibling-matching for any old, still-open PO.
Fixed with a fallback to the line's own `product.generic_name_id` when the new column is null. Caught by
deliberately testing against a real legacy PO item during verification, not just the new code path.
