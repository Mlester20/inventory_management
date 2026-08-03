<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    /**
     * Apply a customer's available advance credit (unconsumed 'advance'
     * CustomerPayment amounts) against a newly created invoice, oldest
     * credit first. A single advance payment can be split across more than
     * one invoice — consumed_amount tracks how much of it remains. Call
     * this right after creating an Invoice that has a customer_id.
     */
    public function applyAvailableCredit(Invoice $invoice): void
    {
        if (!$invoice->customer_id) {
            return;
        }

        $remaining = (float) $invoice->amount_due - (float) $invoice->amount_paid;
        if ($remaining <= 0) {
            return;
        }

        $credits = CustomerPayment::where('customer_id', $invoice->customer_id)
            ->where('type', 'advance')
            ->whereColumn('consumed_amount', '<', 'amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $credit->amount - (float) $credit->consumed_amount;
            $applied = min($remaining, $available);

            $credit->increment('consumed_amount', round($applied, 2));
            $invoice->increment('amount_paid', round($applied, 2));
            $remaining -= $applied;
        }
    }

    /**
     * Record a payment for a customer. A 'collection' payment is applied
     * FIFO against the customer's oldest unpaid invoices — no invoice
     * selection needed on the payment form itself.
     */
    public function recordPayment(Customer $customer, array $data, ?int $userId = null): CustomerPayment
    {
        return DB::transaction(function () use ($customer, $data, $userId) {
            $payment = $customer->payments()->create([
                'type' => $data['type'],
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'prepared_by' => $userId,
            ]);

            if ($data['type'] === 'collection') {
                $this->applyFifo($customer, (float) $data['amount']);
            }

            return $payment;
        });
    }

    /**
     * Apply an amount against a customer's oldest unpaid invoices in order,
     * incrementing each invoice's amount_paid until the amount is exhausted.
     * No per-payment allocation record is kept — only the aggregate
     * CustomerPayment row and each invoice's running amount_paid.
     */
    protected function applyFifo(Customer $customer, float $amount): void
    {
        $remaining = $amount;

        $invoices = $customer->invoices()
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = (float) $invoice->amount_due - (float) $invoice->amount_paid;
            $applied = min($remaining, $outstanding);

            $invoice->increment('amount_paid', round($applied, 2));
            $remaining -= $applied;
        }
    }
}
