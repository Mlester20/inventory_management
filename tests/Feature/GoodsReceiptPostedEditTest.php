<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\GenericName;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoodsReceiptPostedEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Supplier $supplier;
    protected Location $warehouse;

    protected function beforeRefreshingDatabase(): void
    {
        $name = (string) config('database.connections.' . config('database.default') . '.database');

        if ($name !== ':memory:' && ! str_ends_with($name, '_test')) {
            throw new \RuntimeException("Refusing to refresh '{$name}': point this test at a scratch database whose name ends in _test.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Location::warehouse();
    }

    protected function product(string $name = 'TEST ITEM'): Product
    {
        $category = Category::factory()->create();
        $generic = GenericName::factory()->create([
            'category_id' => $category->id,
            'generic_name' => $name . ' ' . uniqid(),
        ]);

        return Product::factory()->create(['generic_name_id' => $generic->id]);
    }

    protected function receipt(Product $product, int $qty = 10, string $lot = 'LOT-A', ?string $expiry = '2028-01-31', float $cost = 5): GoodsReceipt
    {
        return app(GoodsReceiptService::class)->createGoodsReceipt([
            'supplier_id' => $this->supplier->id,
            'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [[
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_cost' => $cost,
                'batch_no' => $lot,
                'expiration_date' => $expiry,
                'remarks' => 'original remarks',
            ]],
        ], $this->admin->id)->fresh(['items.productBatch']);
    }

    protected function revise(GoodsReceipt $receipt, array $lineChanges = [], array $headerChanges = []): array
    {
        $receipt = $receipt->fresh(['items.productBatch']);
        $line = $receipt->items->first();

        $lineData = array_merge([
            'qty' => (int) $line->qty,
            'unit_cost' => (float) $line->unit_cost,
            'batch_no' => $line->batch_no,
            'expiration_date' => $line->expiration_date?->toDateString(),
            'remarks' => $line->remarks,
        ], $lineChanges);
        $header = array_merge([
            'receipt_date' => $receipt->receipt_date->toDateString(),
            'prepared_by' => $receipt->prepared_by,
            'supplier_id' => $receipt->supplier_id,
        ], $headerChanges);

        return app(GoodsReceiptService::class)->reviseGoodsReceipt(
            $receipt,
            $header,
            [$line->id => $lineData],
            $this->admin->id
        );
    }

    protected function stock(Product $product): int
    {
        return (int) $product->fresh('batches')->batches
            ->sum(fn ($batch) => $batch->qtyAtLocation($this->warehouse->id));
    }

    protected function recordLaterMovementPair(GoodsReceipt $receipt): void
    {
        $batch = $receipt->fresh('items.productBatch')->items->first()->productBatch;
        $stock = app(StockService::class);

        $stock->deduct($batch, 1, $this->warehouse, 'Later movement out', $this->admin->id);
        $stock->restock($batch, 1, $this->warehouse, 'Later movement in', $this->admin->id);
    }

    protected function assertRevisionRejected(GoodsReceipt $receipt, array $lineChanges = [], array $headerChanges = []): void
    {
        try {
            $this->revise($receipt, $lineChanges, $headerChanges);
            $this->fail('The posted Goods Receipt revision should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revise', $exception->errors());
        }
    }

    protected function linkPurchaseInvoice(GoodsReceipt $receipt): void
    {
        $receipt->purchaseInvoices()->create([
            'supplier_id' => $receipt->supplier_id,
            'invoice_no' => 'PI-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'amount' => 100,
        ]);
    }

    public function test_posted_receipt_allows_safe_supplier_date_and_remarks_edits_without_invoice_or_later_movement(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product);
        $otherSupplier = Supplier::factory()->create();
        $stockBefore = $this->stock($product);
        $newDate = now()->subDay()->toDateString();

        $this->revise($receipt, ['remarks' => 'corrected remarks'], [
            'supplier_id' => $otherSupplier->id,
            'receipt_date' => $newDate,
        ]);

        $receipt = $receipt->fresh(['items']);
        $this->assertSame($otherSupplier->id, $receipt->supplier_id);
        $this->assertSame($newDate, $receipt->receipt_date->toDateString());
        $this->assertSame('corrected remarks', $receipt->items->first()->remarks);
        $this->assertSame($stockBefore, $this->stock($product));
    }

    public function test_posted_receipt_allows_unit_cost_edit_without_invoice_or_later_movement(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, cost: 5);

        $this->revise($receipt, ['unit_cost' => 7.5]);

        $this->assertEquals(7.5, (float) $receipt->fresh('items')->items->first()->unit_cost);
        $this->assertEquals(7.5, (float) $product->fresh()->unit_cost);
    }

    public function test_supplier_change_is_rejected_when_purchase_invoice_is_linked(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product);
        $otherSupplier = Supplier::factory()->create();
        $this->linkPurchaseInvoice($receipt);

        $this->assertRevisionRejected($receipt, [], ['supplier_id' => $otherSupplier->id]);

        $this->assertSame($this->supplier->id, $receipt->fresh()->supplier_id);
    }

    public function test_supplier_change_is_rejected_after_stock_was_consumed(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product);
        $batch = $receipt->items->first()->productBatch;
        app(StockService::class)->deduct($batch, 1, $this->warehouse, 'Consumed after GR', $this->admin->id);
        $otherSupplier = Supplier::factory()->create();

        $this->assertRevisionRejected($receipt, [], ['supplier_id' => $otherSupplier->id]);

        $this->assertSame($this->supplier->id, $receipt->fresh()->supplier_id);
    }

    public function test_unit_cost_change_is_rejected_when_purchase_invoice_is_linked(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, cost: 5);
        $this->linkPurchaseInvoice($receipt);

        $this->assertRevisionRejected($receipt, ['unit_cost' => 7.5]);

        $this->assertEquals(5, (float) $receipt->fresh('items')->items->first()->unit_cost);
    }

    public function test_unit_cost_change_is_rejected_after_stock_was_consumed(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, cost: 5);
        $batch = $receipt->items->first()->productBatch;
        app(StockService::class)->deduct($batch, 1, $this->warehouse, 'Consumed after GR', $this->admin->id);

        $this->assertRevisionRejected($receipt, ['unit_cost' => 7.5]);

        $this->assertEquals(5, (float) $receipt->fresh('items')->items->first()->unit_cost);
        $this->assertEquals(5, (float) $product->fresh()->unit_cost);
    }

    public function test_quantity_correction_is_blocked_after_later_stock_movement(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, qty: 10);
        $this->recordLaterMovementPair($receipt);

        $this->assertSame(10, $this->stock($product), 'Later movements leave enough stock for the current balance-only guard.');
        $this->assertRevisionRejected($receipt, ['qty' => 9]);

        $this->assertSame(10, $this->stock($product));
        $this->assertSame(10, (int) $receipt->fresh('items')->items->first()->qty);
    }

    public function test_lot_correction_is_blocked_after_later_stock_movement(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, qty: 10, lot: 'LOT-A');
        $this->recordLaterMovementPair($receipt);

        $this->assertRevisionRejected($receipt, ['batch_no' => 'LOT-B']);

        $this->assertSame('LOT-A', $receipt->fresh('items')->items->first()->batch_no);
        $this->assertSame(10, $this->stock($product));
    }

    public function test_expiry_correction_is_blocked_after_later_stock_movement(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, qty: 10, expiry: '2028-01-31');
        $this->recordLaterMovementPair($receipt);

        $this->assertRevisionRejected($receipt, ['expiration_date' => '2029-06-30']);

        $this->assertSame('2028-01-31', $receipt->fresh('items')->items->first()->expiration_date->toDateString());
        $this->assertSame(10, $this->stock($product));
    }

    public function test_quantity_and_lot_corrections_create_movements_and_expiry_only_updates_metadata(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, qty: 10, lot: 'LOT-A', expiry: '2028-01-31');

        $this->revise($receipt, ['qty' => 12]);
        $this->revise($receipt->fresh('items'), [
            'qty' => 12,
            'batch_no' => 'LOT-B',
            'expiration_date' => '2029-01-31',
        ]);

        $receipt = $receipt->fresh('items.productBatch');
        $batchB = $receipt->items->first()->productBatch;
        $movementQuery = StockMovement::where('source_type', $receipt->getMorphClass())
            ->where('source_id', $receipt->id)
            ->orderBy('id');
        $movementCount = $movementQuery->count();

        $this->assertSame([10, 2, -12, 12], $movementQuery->pluck('quantity')->map(fn ($qty) => (int) $qty)->all());
        $this->assertSame(12, $batchB->qtyAtLocation($this->warehouse->id));
        $this->assertSame(0, $product->batches()->where('batch_no', 'LOT-A')->first()->qtyAtLocation($this->warehouse->id));

        $this->revise($receipt, [
            'qty' => 12,
            'batch_no' => 'LOT-B',
            'expiration_date' => '2030-01-31',
        ]);

        $this->assertSame('2030-01-31', $batchB->fresh()->expiration_date->toDateString());
        $this->assertSame($movementCount, $movementQuery->count(), 'Expiry-only metadata correction must not create a stock movement.');
    }

    public function test_over_http_posted_edit_records_an_activity_log_entry(): void
    {
        $product = $this->product();
        $receipt = $this->receipt($product, qty: 10);
        $line = $receipt->items->first();
        $reason = 'Corrected receipt remarks';

        $response = $this->actingAs($this->admin)
            ->post(route('goods-receipts.apply-revision', $receipt), [
                'reason' => $reason,
                'receipt_date' => now()->toDateString(),
                'prepared_by' => $this->admin->id,
                'supplier_id' => $receipt->supplier_id,
                'lines' => [$line->id => [
                    'qty' => 10,
                    'unit_cost' => 5,
                    'batch_no' => 'LOT-A',
                    'expiration_date' => '2028-01-31',
                    'remarks' => 'audited remarks',
                ]],
            ]);

        $response->assertRedirect(route('goods-receipts.show', $receipt));

        $log = ActivityLog::where('module', 'GoodsReceipt')
            ->where('action', 'revised')
            ->where('loggable_id', $receipt->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString($reason, $log->description);
        $this->assertSame($reason, $log->metadata['reason']);
        $this->assertNotEmpty($log->metadata['changes']);
    }
}
