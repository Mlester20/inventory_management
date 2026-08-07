# Goods Receipt "Against Purchase Order" — Brand Field Scope

**Status: Option 1 implemented and live. Option 2 pending Sir's confirmation.**

## Background

Sir flagged that a PO might be raised for one brand of a product, but the supplier sometimes delivers a
different brand of the same generic item. A "Brand" field was added to each pending line in the Goods
Receipt "Against Purchase Order" tab so the receiver can record whichever brand actually arrived — see
`resources/views/admin/goods-receipts/create.blade.php` (the `po-brand-input`/`brandSiblings` logic) and
the supporting API change in `app/Http/Controllers/Api/PurchaseOrderController.php::pendingItems()`.

## The open question

How wide should the Brand field's options be?

- **Option 1 — scoped to the same Generic Item (implemented now).** If the PO was for Paracetamol, the
  Brand field only offers other Paracetamol brands. You can't swap to an unrelated item.
- **Option 2 — open to the full catalog, like Direct Receipt.** The Brand field would let you pick *any*
  product from the whole catalog, not just siblings of the PO line's generic item — matching how the
  Direct Receipt tab's item picker already works (no restriction at all).

## Decision so far

Asked Sir to confirm which one he actually wants. His reply (relayed): **go with Option 1 for now**, and
revisit Option 2 once he's had a chance to use the current version and confirms he wants it.

**Nothing else needs to change until Sir confirms Option 2.** This file exists so that confirmation doesn't
get lost track of in the meantime.

## What changes if Option 2 gets confirmed

Small, contained change — same technique, wider filter. In
`resources/views/admin/goods-receipts/create.blade.php`, inside the Against-PO line-rendering code:

```js
// Current (Option 1):
const brandSiblings = ITEMS.filter(i => i.generic_name_id != null && i.generic_name_id === line.generic_name_id);

// Option 2 — open to the full catalog:
const brandSiblings = ITEMS;
```

That's the only line that needs to change. Everything else (the datalist rendering, the hidden
`product_id` sync on selection, the unit-cost auto-fill) stays exactly as-is, since it already works off
whatever list `brandSiblings` resolves to.
