<?php

namespace Tests\Feature;

use App\Models\Item;
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
    protected Item $item;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);

        // Create test data
        $category = \App\Models\Category::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        
        $this->item = Item::factory()->create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'quantity' => 50,
            'low_stock_threshold' => 20,
        ]);

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->user);
    }

    /**
     * Test that item quantity is updated without creating duplicate
     */
    public function test_restock_increases_quantity_without_duplicate_item(): void
    {
        $initialItemCount = Item::count();
        $initialQuantity = $this->item->quantity;

        $this->stockService->restock($this->item, 100);

        // Should NOT create new item
        $this->assertEquals($initialItemCount, Item::count());

        // Should increase existing item quantity
        $this->item->refresh();
        $this->assertEquals($initialQuantity + 100, $this->item->quantity);
    }

    /**
     * Test that stock movement is created on restock
     */
    public function test_restock_creates_stock_movement_record(): void
    {
        $this->stockService->restock($this->item, 100, 'Restock test');

        $movement = StockMovement::where('item_id', $this->item->id)
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
        $this->stockService->restock($this->item, -50);
    }

    /**
     * Test that restock validates non-zero quantity
     */
    public function test_restock_validates_non_zero_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->stockService->restock($this->item, 0);
    }

    /**
     * Test deduction decreases quantity
     */
    public function test_deduct_decreases_quantity(): void
    {
        $initialQuantity = $this->item->quantity;

        $this->stockService->deduct($this->item, 25);

        $this->item->refresh();
        $this->assertEquals($initialQuantity - 25, $this->item->quantity);
    }

    /**
     * Test deduction creates negative stock movement
     */
    public function test_deduct_creates_negative_stock_movement(): void
    {
        $this->stockService->deduct($this->item, 25, 'Sale order');

        $movement = StockMovement::where('item_id', $this->item->id)
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
        $this->item->update(['quantity' => 10]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->stockService->deduct($this->item, 25);
    }

    /**
     * Test deduction allows exact quantity available
     */
    public function test_deduct_allows_exact_quantity_available(): void
    {
        $this->item->update(['quantity' => 25]);

        $movement = $this->stockService->deduct($this->item, 25);

        $this->item->refresh();
        $this->assertEquals(0, $this->item->quantity);
        $this->assertNotNull($movement);
    }

    /**
     * Test adjust increases stock
     */
    public function test_adjust_increases_stock(): void
    {
        $this->item->update(['quantity' => 50]);

        $movement = $this->stockService->adjust($this->item, 100, 'Stock audit');

        $this->item->refresh();
        $this->assertEquals(100, $this->item->quantity);
        $this->assertEquals(50, $movement->quantity); // Positive difference
        $this->assertEquals('in', $movement->type);
    }

    /**
     * Test adjust decreases stock
     */
    public function test_adjust_decreases_stock(): void
    {
        $this->item->update(['quantity' => 100]);

        $movement = $this->stockService->adjust($this->item, 50, 'Stock audit');

        $this->item->refresh();
        $this->assertEquals(50, $this->item->quantity);
        $this->assertEquals(-50, $movement->quantity); // Negative difference
        $this->assertEquals('out', $movement->type);
    }

    /**
     * Test adjust with no change returns null
     */
    public function test_adjust_no_change_returns_null(): void
    {
        $this->item->update(['quantity' => 50]);

        $movement = $this->stockService->adjust($this->item, 50);

        $this->assertNull($movement);
    }

    /**
     * Test adjust prevents negative quantity
     */
    public function test_adjust_prevents_negative_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->stockService->adjust($this->item, -10);
    }

    /**
     * Test low stock detection
     */
    public function test_is_low_on_stock_returns_true(): void
    {
        $this->item->update(['quantity' => 15, 'low_stock_threshold' => 20]);

        $this->assertTrue($this->item->isLowOnStock());
    }

    /**
     * Test low stock detection at threshold
     */
    public function test_is_low_on_stock_at_threshold(): void
    {
        $this->item->update(['quantity' => 20, 'low_stock_threshold' => 20]);

        $this->assertTrue($this->item->isLowOnStock());
    }

    /**
     * Test low stock detection above threshold
     */
    public function test_is_low_on_stock_above_threshold(): void
    {
        $this->item->update(['quantity' => 25, 'low_stock_threshold' => 20]);

        $this->assertFalse($this->item->isLowOnStock());
    }

    /**
     * Test getting low stock items
     */
    public function test_get_low_stock_items(): void
    {
        $this->item->update(['quantity' => 15, 'low_stock_threshold' => 20]);

        $lowStockItems = $this->stockService->getLowStockItems();

        $this->assertTrue($lowStockItems->contains($this->item));
    }

    /**
     * Test getting out of stock items
     */
    public function test_get_out_of_stock_items(): void
    {
        $this->item->update(['quantity' => 0]);

        $outOfStock = $this->stockService->getOutOfStockItems();

        $this->assertTrue($outOfStock->contains($this->item));
    }

    /**
     * Test movement history retrieval
     */
    public function test_get_movement_history(): void
    {
        $this->stockService->restock($this->item, 50);
        $this->stockService->deduct($this->item, 20);
        $this->stockService->restock($this->item, 30);

        $history = $this->stockService->getMovementHistory($this->item);

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
                'item_id' => $this->item->id,
                'user_id' => $this->user->id,
                'quantity' => 1,
                'type' => 'in',
            ]);
        }

        $history = $this->stockService->getMovementHistory($this->item, 50);

        $this->assertCount(50, $history);
    }

    /**
     * Test user tracking in stock movements
     */
    public function test_stock_movement_tracks_user(): void
    {
        $movement = $this->stockService->restock($this->item, 100);

        $this->assertEquals($this->user->id, $movement->user_id);
        $this->assertEquals($this->user->id, $movement->user->id);
    }

    /**
     * Test multiple restocks on same item
     */
    public function test_multiple_restocks_update_same_item(): void
    {
        $initialItemCount = Item::count();
        $this->item->update(['quantity' => 100]);

        $this->stockService->restock($this->item, 50);
        $this->stockService->restock($this->item, 75);
        $this->stockService->restock($this->item, 25);

        // Still only 1 item record
        $this->assertEquals($initialItemCount, Item::count());

        // Movements added
        $this->item->refresh();
        $this->assertEquals(100 + 50 + 75 + 25, $this->item->quantity);
        $this->assertCount(3, $this->item->stockMovements);
    }

    /**
     * Test transaction rollback on error
     */
    public function test_transaction_rollback_on_error(): void
    {
        $this->item->update(['quantity' => 10]);

        try {
            $this->stockService->deduct($this->item, 25); // Will fail
        } catch (ValidationException $e) {
            // Expected
        }

        $this->item->refresh();
        // Quantity should remain unchanged due to rollback
        $this->assertEquals(10, $this->item->quantity);
        // No movement should be created
        $this->assertCount(0, $this->item->stockMovements);
    }

    /**
     * Test Item scope for low stock
     */
    public function test_item_low_stock_scope(): void
    {
        Item::factory()->create([
            'quantity' => 5,
            'low_stock_threshold' => 10,
        ]);

        Item::factory()->create([
            'quantity' => 50,
            'low_stock_threshold' => 10,
        ]);

        $lowStock = Item::lowStock()->get();

        $this->assertGreaterThan(0, $lowStock->count());
        $this->assertTrue($lowStock->every(fn($item) => $item->quantity <= $item->low_stock_threshold));
    }

    /**
     * Test total restocked calculation
     */
    public function test_total_restocked_attribute(): void
    {
        $this->stockService->restock($this->item, 50);
        $this->stockService->restock($this->item, 75);

        $this->item->refresh();

        $this->assertEquals(125, $this->item->total_restocked);
    }

    /**
     * Test total deducted calculation
     */
    public function test_total_deducted_attribute(): void
    {
        $this->item->update(['quantity' => 200]);

        $this->stockService->deduct($this->item, 50);
        $this->stockService->deduct($this->item, 30);

        $this->item->refresh();

        $this->assertEquals(80, $this->item->total_deducted);
    }
}
