<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    /**
     * Cancel/void a Sales Order — the alternative to delete() when it
     * already has delivery history and can't just be removed. Per Sir's
     * direction: every linked Delivery Receipt gets a note explaining why
     * (the DR's own status is untouched — it still shows what actually
     * happened physically), and any Invoice already created from one of
     * those DRs is auto-cancelled too, since an invoice billing for a now
     * -void order shouldn't stay active.
     *
     * @return array{delivery_receipts: \Illuminate\Support\Collection, invoices: \Illuminate\Support\Collection}
     */
    public function cancel(SalesOrder $salesOrder): array
    {
        return DB::transaction(function () use ($salesOrder) {
            $salesOrder->update(['status' => 'cancelled']);

            $affectedDeliveryReceipts = collect();
            $affectedInvoices = collect();

            foreach ($salesOrder->deliveryReceipts as $deliveryReceipt) {
                $deliveryReceipt->update([
                    'cancellation_note' => "Sales Order {$salesOrder->so_no} linked to this delivery was cancelled/voided on " . now()->format('M d, Y') . '.',
                ]);
                $affectedDeliveryReceipts->push($deliveryReceipt);

                $invoiceIds = $deliveryReceipt->items()
                    ->with('sales')
                    ->get()
                    ->pluck('sales')
                    ->flatten()
                    ->pluck('invoice_id')
                    ->filter()
                    ->unique();

                foreach ($invoiceIds as $invoiceId) {
                    $invoice = Invoice::find($invoiceId);

                    if ($invoice && ! $invoice->isCancelled()) {
                        $invoice->update(['cancelled_at' => now()]);
                        $affectedInvoices->push($invoice);
                    }
                }
            }

            return [
                'delivery_receipts' => $affectedDeliveryReceipts,
                'invoices' => $affectedInvoices->unique('id'),
            ];
        });
    }

    /**
     * Create a Sales Order with its line items.
     *
     * @param array $data ['customer_id', 'po_no', 'order_date', 'prepared_by', 'items' => [['generic_name_id','qty','price','advance_order_qty'], ...]]
     */
    public function createSalesOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $salesOrder = SalesOrder::create([
                'customer_id' => $data['customer_id'],
                'so_no' => $this->generateSoNo(),
                'po_no' => $data['po_no'] ?? null,
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyItems($salesOrder, $data['items']);

            return $salesOrder;
        });
    }

    /**
     * Save (or re-save) a draft — nothing here ever touches SO status or
     * delivered_qty tracking, so the encoder can leave customer/items blank/
     * incomplete and resume later.
     *
     * @param array $data ['customer_id', 'po_no', 'order_date', 'prepared_by', 'items' => [['generic_name_id','qty','price','advance_order_qty'], ...]]
     */
    public function saveDraft(array $data, ?SalesOrder $existing = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $existing) {
            $salesOrder = $existing ?? new SalesOrder([
                'so_no' => $this->generateSoNo(),
            ]);

            $salesOrder->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'po_no' => $data['po_no'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => true,
                'notes' => $data['notes'] ?? null,
            ]);
            $salesOrder->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been delivered against.
            $salesOrder->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['generic_name_id'])) {
                    continue;
                }

                $salesOrder->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'qty' => $line['qty'] ?? null,
                    'price' => $line['price'] ?? null,
                    'advance_order_qty' => $line['advance_order_qty'] ?? 0,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * Turn a draft into a real, posted Sales Order. Replaces the draft's
     * items with the final values, then runs the same item-creation logic
     * createSalesOrder() uses.
     */
    public function finalizeDraft(SalesOrder $draft, array $data): SalesOrder
    {
        return DB::transaction(function () use ($draft, $data) {
            $draft->fill([
                'customer_id' => $data['customer_id'],
                'po_no' => $data['po_no'] ?? null,
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items']);

            return $draft;
        });
    }

    /**
     * Create each line item — shared by createSalesOrder() and
     * finalizeDraft() so this logic exists in exactly one place.
     */
    protected function applyItems(SalesOrder $salesOrder, array $items): void
    {
        foreach ($items as $line) {
            $salesOrder->items()->create([
                'generic_name_id' => $line['generic_name_id'],
                'qty' => $line['qty'],
                'price' => $line['price'],
                'advance_order_qty' => $line['advance_order_qty'] ?? 0,
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }

    /**
     * Generate the next sequential Sales Order number for the current year,
     * e.g. SO-2026-00001.
     */
    public function generateSoNo(): string
    {
        $year = now()->year;
        $prefix = "SO-{$year}-";

        $lastSoNo = SalesOrder::where('so_no', 'like', "{$prefix}%")
            ->orderByDesc('so_no')
            ->value('so_no');

        $nextSequence = 1;
        if ($lastSoNo) {
            $nextSequence = (int) substr($lastSoNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
