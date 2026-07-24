<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected Product $product;
    protected ProductBatch $batch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);

        // Create test data
        $category = \App\Models\Category::factory()->create();
        $genericName = \App\Models\GenericName::factory()->create(['category_id' => $category->id]);
        $supplier = \App\Models\Supplier::factory()->create();

        $this->product = Product::factory()->create([
            'generic_name_id' => $genericName->id,
            'supplier_id' => $supplier->id,
            'low_stock_threshold' => 20,
        ]);

        $this->batch = $this->product->batches()->create([
            'qty' => 50,
            'reserved_qty' => 0,
        ]);

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->user);
    }

    /**
     * Test that batch quantity is updated without creating duplicate batch
     */
    public function test_restock_increases_quantity_without_duplicate_batch(): void
    {
        $initialBatchCount = ProductBatch::count();
        $initialQuantity = $this->batch->qty;

        $this->stockService->restock($this->batch, 100);

        // Should NOT create new batch
        $this->assertEquals($initialBatchCount, ProductBatch::count());

        // Should increase existing batch quantity
        $this->batch->refresh();
        $this->assertEquals($initialQuantity + 100, $this->batch->qty);
    }

    /**
     * Test that stock movement is created on restock
     */
    public function test_restock_creates_stock_movement_record(): void
    {
        $this->stockService->restock($this->batch, 100, 'Restock test');

        $movement = StockMovement::where('product_batch_id', $this->batch->id)
            ->where('type', 'in')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(100, $movement->quantity);
        $this->assertEquals('in', $movement->type);
        $this->assertEquals('Restock test', $movement->remarks);
    }

    /**
     * Test that restock validates positive quantity
     */
    public function test_restock_validates_positive_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->stockService->restock($this->batch, -50);
    }

    /**
     * Test that restock validates non-zero quantity
     */
    public function test_restock_validates_non_zero_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->stockService->restock($this->batch, 0);
    }

    /**
     * Test deduction decreases quantity
     */
    public function test_deduct_decreases_quantity(): void
    {
        $initialQuantity = $this->batch->qty;

        $this->stockService->deduct($this->batch, 25);

        $this->batch->refresh();
        $this->assertEquals($initialQuantity - 25, $this->batch->qty);
    }

    /**
     * Test deduction creates negative stock movement
     */
    public function test_deduct_creates_negative_stock_movement(): void
    {
        $this->stockService->deduct($this->batch, 25, 'Sale order');

        $movement = StockMovement::where('product_batch_id', $this->batch->id)
            ->where('type', 'out')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-25, $movement->quantity); // Negative value
        $this->assertEquals('out', $movement->type);
    }

    /**
     * Test deduction prevents stock from going below 0
     */
    public function test_deduct_prevents_negative_stock(): void
    {
        $this->batch->update(['qty' => 10]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->stockService->deduct($this->batch, 25);
    }

    /**
     * Test deduction allows exact quantity available
     */
    public function test_deduct_allows_exact_quantity_available(): void
    {
        $this->batch->update(['qty' => 25]);

        $movement = $this->stockService->deduct($this->batch, 25);

        $this->batch->refresh();
        $this->assertEquals(0, $this->batch->qty);
        $this->assertNotNull($movement);
    }

    /**
     * Test adjust increases stock
     */
    public function test_adjust_increases_stock(): void
    {
        $this->batch->update(['qty' => 50]);

        $movement = $this->stockService->adjust($this->batch, 100, 'Stock audit');

        $this->batch->refresh();
        $this->assertEquals(100, $this->batch->qty);
        $this->assertEquals(50, $movement->quantity); // Positive difference
        $this->assertEquals('in', $movement->type);
    }

    /**
     * Test adjust decreases stock
     */
    public function test_adjust_decreases_stock(): void
    {
        $this->batch->update(['qty' => 100]);

        $movement = $this->stockService->adjust($this->batch, 50, 'Stock audit');

        $this->batch->refresh();
        $this->assertEquals(50, $this->batch->qty);
        $this->assertEquals(-50, $movement->quantity); // Negative difference
        $this->assertEquals('out', $movement->type);
    }

    /**
     * Test adjust with no change returns null
     */
    public function test_adjust_no_change_returns_null(): void
    {
        $this->batch->update(['qty' => 50]);

        $movement = $this->stockService->adjust($this->batch, 50);

        $this->assertNull($movement);
    }

    /**
     * Test adjust prevents negative quantity
     */
    public function test_adjust_prevents_negative_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->stockService->adjust($this->batch, -10);
    }

    /**
     * Test low stock detection
     */
    public function test_is_low_on_stock_returns_true(): void
    {
        $this->batch->update(['qty' => 15]);
        $this->product->update(['low_stock_threshold' => 20]);

        $this->assertTrue($this->product->isLowOnStock());
    }

    /**
     * Test low stock detection at threshold
     */
    public function test_is_low_on_stock_at_threshold(): void
    {
        $this->batch->update(['qty' => 20]);
        $this->product->update(['low_stock_threshold' => 20]);

        $this->assertTrue($this->product->isLowOnStock());
    }

    /**
     * Test low stock detection above threshold
     */
    public function test_is_low_on_stock_above_threshold(): void
    {
        $this->batch->update(['qty' => 25]);
        $this->product->update(['low_stock_threshold' => 20]);

        $this->assertFalse($this->product->isLowOnStock());
    }

    /**
     * Test getting low stock products
     */
    public function test_get_low_stock_items(): void
    {
        $this->batch->update(['qty' => 15]);
        $this->product->update(['low_stock_threshold' => 20]);

        $lowStockItems = $this->stockService->getLowStockItems();

        $this->assertTrue($lowStockItems->contains($this->product));
    }

    /**
     * Test getting out of stock products
     */
    public function test_get_out_of_stock_items(): void
    {
        $this->batch->update(['qty' => 0]);

        $outOfStock = $this->stockService->getOutOfStockItems();

        $this->assertTrue($outOfStock->contains($this->product));
    }

    /**
     * Test movement history retrieval
     */
    public function test_get_movement_history(): void
    {
        $this->stockService->restock($this->batch, 50);
        $this->stockService->deduct($this->batch, 20);
        $this->stockService->restock($this->batch, 30);

        $history = $this->stockService->getMovementHistory($this->batch);

        $this->assertCount(3, $history);

        // Verify all three movements are present
        $quantities = $history->pluck('quantity')->toArray();
        $types = $history->pluck('type')->toArray();

        // Should have all three movements
        $this->assertContains(50, $quantities);
        $this->assertContains(-20, $quantities);
        $this->assertContains(30, $quantities);

        // Should have correct types
        $this->assertContains('in', $types);
        $this->assertContains('out', $types);
    }

    /**
     * Test movement history limits results
     */
    public function test_get_movement_history_limits_results(): void
    {
        // Create 60 movements
        for ($i = 0; $i < 60; $i++) {
            StockMovement::create([
                'product_batch_id' => $this->batch->id,
                'user_id' => $this->user->id,
                'quantity' => 1,
                'type' => 'in',
            ]);
        }

        $history = $this->stockService->getMovementHistory($this->batch, 50);

        $this->assertCount(50, $history);
    }

    /**
     * Test user tracking in stock movements
     */
    public function test_stock_movement_tracks_user(): void
    {
        $movement = $this->stockService->restock($this->batch, 100);

        $this->assertEquals($this->user->id, $movement->user_id);
        $this->assertEquals($this->user->id, $movement->user->id);
    }

    /**
     * Test multiple restocks on same batch
     */
    public function test_multiple_restocks_update_same_batch(): void
    {
        $initialBatchCount = ProductBatch::count();
        $this->batch->update(['qty' => 100]);

        $this->stockService->restock($this->batch, 50);
        $this->stockService->restock($this->batch, 75);
        $this->stockService->restock($this->batch, 25);

        // Still only 1 batch record
        $this->assertEquals($initialBatchCount, ProductBatch::count());

        // Movements added
        $this->batch->refresh();
        $this->assertEquals(100 + 50 + 75 + 25, $this->batch->qty);
        $this->assertCount(3, $this->batch->stockMovements);
    }

    /**
     * Test transaction rollback on error
     */
    public function test_transaction_rollback_on_error(): void
    {
        $this->batch->update(['qty' => 10]);

        try {
            $this->stockService->deduct($this->batch, 25); // Will fail
        } catch (ValidationException $e) {
            // Expected
        }

        $this->batch->refresh();
        // Quantity should remain unchanged due to rollback
        $this->assertEquals(10, $this->batch->qty);
        // No movement should be created
        $this->assertCount(0, $this->batch->stockMovements);
    }

    /**
     * Test Product scope for low stock
     */
    public function test_product_low_stock_scope(): void
    {
        $lowProduct = Product::factory()->create(['low_stock_threshold' => 10]);
        $lowProduct->batches()->create(['qty' => 5, 'reserved_qty' => 0]);

        $highProduct = Product::factory()->create(['low_stock_threshold' => 10]);
        $highProduct->batches()->create(['qty' => 50, 'reserved_qty' => 0]);

        $lowStock = Product::lowStock()->get();

        $this->assertGreaterThan(0, $lowStock->count());
        $this->assertTrue($lowStock->every(fn ($product) => $product->quantity <= $product->low_stock_threshold));
    }

    /**
     * Test total restocked calculation
     */
    public function test_total_restocked_attribute(): void
    {
        $this->stockService->restock($this->batch, 50);
        $this->stockService->restock($this->batch, 75);

        $this->batch->refresh();

        $this->assertEquals(125, (int) $this->batch->restockMovements()->sum('quantity'));
    }

    /**
     * Test total deducted calculation
     */
    public function test_total_deducted_attribute(): void
    {
        $this->batch->update(['qty' => 200]);

        $this->stockService->deduct($this->batch, 50);
        $this->stockService->deduct($this->batch, 30);

        $this->batch->refresh();

        $this->assertEquals(80, (int) abs($this->batch->deductionMovements()->sum('quantity')));
    }
}
