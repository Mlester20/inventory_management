<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\ReturnItemService;
use App\Services\SalesQuoteService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regression coverage for a class of bug found during manual testing: a
 * status-transitioning action (convert/approve/finalize/invoice) checked a
 * record's status on a model instance that could already be stale by the
 * time the check ran, instead of re-reading it fresh under a lock. A
 * double-click (two near-simultaneous submissions, each starting from its
 * own independently-loaded copy of the same record) could then both pass
 * the check and both apply the action — creating duplicate documents or,
 * worse, silently double-moving real stock with no duplicate record left
 * behind to hint anything went wrong.
 *
 * Each test below simulates that scenario without needing two real
 * concurrent database connections: it loads two separate copies of the
 * same record up front (mirroring two requests that each loaded the record
 * via route-model binding before either committed), runs the action
 * against the first copy, then runs it again against the second — still
 * holding the pre-change status in memory, exactly as a second overlapping
 * request would. The fix under test is that the service re-fetches the
 * record's current state itself rather than trusting the copy it was
 * handed, so the second call correctly sees the already-changed status and
 * rejects instead of duplicating the action.
 */
class ConcurrentActionGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function stockAt(ProductBatch $batch, Location $location, int $qty): void
    {
        $batch->locationStocks()->create([
            'location_id' => $location->id,
            'qty' => $qty,
            'reserved_qty' => 0,
        ]);
    }

    protected function makeBatchWithStock(Location $location, int $qty): ProductBatch
    {
        $generic = GenericName::factory()->create();
        $product = Product::factory()->create(['generic_name_id' => $generic->id]);
        $batch = ProductBatch::factory()->create(['product_id' => $product->id]);
        $this->stockAt($batch, $location, $qty);

        return $batch;
    }

    public function test_converting_an_already_converted_sales_quote_is_rejected_and_does_not_duplicate_the_order(): void
    {
        $customer = Customer::factory()->create();
        $generic = GenericName::factory()->create();

        $service = app(SalesQuoteService::class);

        $quote = $service->createSalesQuote([
            'customer_id' => $customer->id,
            'quote_date' => now()->toDateString(),
            'items' => [
                ['generic_name_id' => $generic->id, 'qty' => 5, 'price' => 10],
            ],
        ]);

        // Two independently-loaded copies, both still status="open" — the
        // race the fix has to close.
        $copyA = \App\Models\SalesQuote::find($quote->id);
        $copyB = \App\Models\SalesQuote::find($quote->id);

        $service->convertToSalesOrder($copyA, ['order_date' => now()->toDateString()]);

        $this->expectException(ValidationException::class);

        try {
            $service->convertToSalesOrder($copyB, ['order_date' => now()->toDateString()]);
        } finally {
            $this->assertEquals(
                1,
                SalesOrder::where('sales_quote_id', $quote->id)->count(),
                'Exactly one Sales Order should exist for this Quote, not two.'
            );
        }
    }

    public function test_approving_an_already_approved_return_item_is_rejected_and_does_not_double_restock(): void
    {
        $posLocation = Location::pos();
        $batch = $this->makeBatchWithStock($posLocation, 0);
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);

        $returnItem = ReturnItem::factory()->create([
            'product_batch_id' => $batch->id,
            'quantity' => 3,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $service = app(ReturnItemService::class);

        $copyA = ReturnItem::find($returnItem->id);
        $copyB = ReturnItem::find($returnItem->id);

        $service->approve($copyA, 'cash', 'sellable', $user->id);

        $this->expectException(ValidationException::class);

        try {
            $service->approve($copyB, 'cash', 'sellable', $user->id);
        } finally {
            $this->assertEquals(
                3,
                $batch->fresh()->qtyAtLocation($posLocation->id),
                'POS stock should reflect only ONE approval (+3), not two (+6).'
            );
        }
    }

    public function test_finalizing_an_already_posted_delivery_receipt_is_rejected_and_does_not_double_deduct_stock(): void
    {
        $warehouse = Location::warehouse();
        $batch = $this->makeBatchWithStock($warehouse, 10);
        $customer = Customer::factory()->create();

        $service = app(DeliveryReceiptService::class);

        $draft = $service->saveDraft([
            'customer_id' => $customer->id,
            'transaction_type' => 'cash',
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_batch_id' => $batch->id, 'qty' => 3],
            ],
        ]);

        $finalizeData = [
            'customer_id' => $customer->id,
            'transaction_type' => 'cash',
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_batch_id' => $batch->id, 'qty' => 3],
            ],
        ];

        // Two independently-loaded copies, both still is_draft=true.
        $copyA = \App\Models\DeliveryReceipt::find($draft->id);
        $copyB = \App\Models\DeliveryReceipt::find($draft->id);

        $service->finalizeDraft($copyA, $finalizeData);

        $this->expectException(ValidationException::class);

        try {
            $service->finalizeDraft($copyB, $finalizeData);
        } finally {
            $this->assertEquals(
                7,
                $batch->fresh()->qtyAtLocation($warehouse->id),
                'Warehouse stock should reflect only ONE finalize (-3 from 10), not two (-6).'
            );
        }
    }

    public function test_invoicing_an_already_fully_invoiced_delivery_receipt_line_is_rejected(): void
    {
        // This covers the business-rule regression (re-invoicing a fully
        // invoiced line is blocked) — the same call this test makes runs
        // sequentially within one connection, so it cannot by itself prove
        // the lockForUpdate() fix holds under two genuinely concurrent
        // database connections the way the tests above do (their race is
        // reproducible from a stale in-memory object alone). The lock was
        // still added as the correct, low-risk fix for that real scenario.
        $warehouse = Location::warehouse();
        $batch = $this->makeBatchWithStock($warehouse, 10);
        $customer = Customer::factory()->create();

        $service = app(DeliveryReceiptService::class);

        $dr = $service->createDeliveryReceipt([
            'customer_id' => $customer->id,
            'transaction_type' => 'cash',
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['product_batch_id' => $batch->id, 'qty' => 4],
            ],
        ]);

        $lineId = $dr->items()->first()->id;

        $service->createInvoiceFromLines($dr->fresh(), [$lineId]);

        $this->expectException(ValidationException::class);
        $service->createInvoiceFromLines($dr->fresh(), [$lineId]);
    }
}
