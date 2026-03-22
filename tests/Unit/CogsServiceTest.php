<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CogsService;
use App\Models\Purchase;
use App\Models\ReturnItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CogsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CogsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CogsService();
    }

    /** @test */
    public function it_returns_zero_cogs_when_no_purchases_exist()
    {
        $result = $this->service->calculate();
        
        $this->assertEquals(0, $result['gross_cogs']);
        $this->assertEquals(0, $result['return_deductions']);
        $this->assertEquals(0, $result['net_cogs']);
    }

    /** @test */
    public function it_calculates_gross_cogs_from_purchases()
    {
        // Create test data
        $user = User::factory()->create();
        $item = Item::factory()->create();
        
        // Create 2 purchases with known quantities and prices
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 100.00,
            'total_price' => 1000.00,
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 5,
            'unit_price' => 50.00,
            'total_price' => 250.00,
        ]);

        $result = $this->service->calculate();
        
        // Expected: (10 * 100) + (5 * 50) = 1000 + 250 = 1250
        $this->assertEquals(1250.00, $result['gross_cogs']);
        $this->assertEquals(0, $result['return_deductions']);
        $this->assertEquals(1250.00, $result['net_cogs']);
    }

    /** @test */
    public function it_deducts_approved_returns_from_net_cogs()
    {
        // Create test data
        $user = User::factory()->create();
        $item = Item::factory()->create(['unit_price' => 50.00]);
        
        // Create a purchase
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 50.00,
            'total_price' => 500.00,
        ]);
        
        // Create an approved return for the same item
        ReturnItem::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity' => 2,
            'status' => 'approved',
        ]);

        $result = $this->service->calculate();
        
        // Expected: gross = 500, returns = (2 * 50) = 100, net = 400
        $this->assertEquals(500.00, $result['gross_cogs']);
        $this->assertEquals(100.00, $result['return_deductions']);
        $this->assertEquals(400.00, $result['net_cogs']);
    }

    /** @test */
    public function it_ignores_pending_and_rejected_returns()
    {
        // Create test data
        $user = User::factory()->create();
        $item = Item::factory()->create(['unit_price' => 50.00]);
        
        // Create a purchase
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 50.00,
            'total_price' => 500.00,
        ]);
        
        // Create returns with status='pending' and status='rejected'
        ReturnItem::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);
        
        ReturnItem::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity' => 3,
            'status' => 'rejected',
        ]);

        $result = $this->service->calculate();
        
        // Expected: gross = 500, returns = 0, net = 500
        $this->assertEquals(500.00, $result['gross_cogs']);
        $this->assertEquals(0, $result['return_deductions']);
        $this->assertEquals(500.00, $result['net_cogs']);
    }

    /** @test */
    public function it_filters_cogs_by_date_range()
    {
        // Create test data
        $user = User::factory()->create();
        $item = Item::factory()->create();
        
        // Create purchases on different dates
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 100.00,
            'purchase_date' => '2026-01-15',
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 5,
            'unit_price' => 50.00,
            'purchase_date' => '2026-02-15',
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 8,
            'unit_price' => 75.00,
            'purchase_date' => '2026-03-15',
        ]);

        // Filter for February only
        $result = $this->service->calculate('2026-02-01', '2026-02-28');
        
        // Expected: only February purchase = (5 * 50) = 250
        $this->assertEquals(250.00, $result['gross_cogs']);
        $this->assertEquals(250.00, $result['net_cogs']);
    }

    /** @test */
    public function it_returns_per_item_breakdown()
    {
        // Create test data
        $user = User::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create(['unit_price' => 75.00]);
        
        // Create purchases for different items
        Purchase::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 100.00,
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item2->id,
            'user_id' => $user->id,
            'quantity_sold' => 5,
            'unit_price' => 75.00,
        ]);

        $result = $this->service->perItem();
        
        $this->assertCount(2, $result);
        $this->assertEquals(1000.00, $result->first()['gross_cogs']);
        $this->assertEquals(375.00, $result->last()['gross_cogs']);
    }

    /** @test */
    public function it_returns_monthly_trend_for_year()
    {
        // Create test data
        $user = User::factory()->create();
        $item = Item::factory()->create();
        
        // Create purchases for different months
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 100.00,
            'purchase_date' => '2026-01-15',
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'quantity_sold' => 5,
            'unit_price' => 50.00,
            'purchase_date' => '2026-03-15',
        ]);

        $result = $this->service->monthlyTrend(2026);
        
        $this->assertCount(12, $result);
        $this->assertEquals('Jan', $result[0]['label']);
        $this->assertEquals(1000.00, $result[0]['net_cogs']);
        $this->assertEquals(250.00, $result[2]['net_cogs']);
        $this->assertEquals(0, $result[1]['net_cogs']);
    }

    /** @test */
    public function it_handles_multiple_items_with_returns()
    {
        // Create test data
        $user = User::factory()->create();
        $item1 = Item::factory()->create(['unit_price' => 50.00]);
        $item2 = Item::factory()->create(['unit_price' => 100.00]);
        
        // Create purchases
        Purchase::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $user->id,
            'quantity_sold' => 20,
            'unit_price' => 50.00,
        ]);
        
        Purchase::factory()->create([
            'item_id' => $item2->id,
            'user_id' => $user->id,
            'quantity_sold' => 10,
            'unit_price' => 100.00,
        ]);
        
        // Create approved returns
        ReturnItem::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $user->id,
            'quantity' => 5,
            'status' => 'approved',
        ]);
        
        ReturnItem::factory()->create([
            'item_id' => $item2->id,
            'user_id' => $user->id,
            'quantity' => 2,
            'status' => 'approved',
        ]);

        $result = $this->service->calculate();
        
        // Expected:
        // Gross COGS = (20 * 50) + (10 * 100) = 1000 + 1000 = 2000
        // Deductions = (5 * 50) + (2 * 100) = 250 + 200 = 450
        // Net COGS = 2000 - 450 = 1550
        $this->assertEquals(2000.00, $result['gross_cogs']);
        $this->assertEquals(450.00, $result['return_deductions']);
        $this->assertEquals(1550.00, $result['net_cogs']);
    }
}
