# Inventory Adjustment — Edit / Delete / Print

**Status: Print built and live (2026-08-13), using the Invoice print layout as the template. Edit/Delete
still blocked on the question below.**

## Why this one needs an answer first (the others didn't)

Once an Inventory Adjustment is saved, it **immediately changes the stock count** — same as Goods
Receipt and Delivery Receipt already do. The system currently has no "undo" for that: there's no Edit
or Delete for Inventory Adjustment at all yet.

Goods Receipt and Delivery Receipt already ran into this exact problem before, and the answer picked
for both of them was: **the Edit button is there, but clicking it just says "editing isn't allowed"
and sends you back** — nothing about the stock gets touched. Delete isn't offered at all for those two.

## The question for Sir

**Dapat ba pwedeng aktwal na i-edit o i-delete ang isang naka-save nang Inventory Adjustment?**

- **Option A — Sundin yung ginawa na sa Goods Receipt/Delivery Receipt (mas ligtas, mas mabilis gawin).**
  May Edit button, pero pag pinindot, may lalabas na "hindi ito pwedeng i-edit" at babalik lang sa
  listahan — walang actual na nabago sa stock. Walang Delete button. Zero risk na magkamali ang stock
  count, pero kung nagkamali ka sa pag-enter ng adjustment, kailangan mo na lang gumawa ng bagong
  (reversing) adjustment para itama, hindi mo puwedeng burahin o i-edit yung mali.

- **Option B — Gawing tunay na pwedeng i-edit/i-delete.** Mas malaking trabaho ito, kasi kailangang
  automatic na "bawiin" muna yung dating stock movement bago ilagay yung bago (o buong bawiin kapag
  Delete). Mas may panganib din — kung may bug sa reversal logic, pwedeng magkamali yung stock count
  nang hindi agad mapapansin.

**Recommend namin: Option A.** Pareho lang naman ang ginagawa sa Goods Receipt at Delivery Receipt
ngayon, kaya consistent, at zero risk sa totoong stock data.

## Print — done

Built directly using Invoice's print layout as the template (letterhead + company info, doc title box,
adjustment details, items table, signature block) — no customer/supplier "TO" box or money totals,
since an Inventory Adjustment is an internal document with neither. Live-verified with a temp record
(HTTP round trip, print elements confirmed rendered, all test data cleaned up).
