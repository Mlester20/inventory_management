<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's Goods Receipt concern: receive only the lines that arrived, and Close a Purchase Order
 * whose remaining items will never come (Reopen undoes it).
 */
class PurchaseOrderCloseAndPartialReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Supplier $supplier;
    protected PurchaseOrderService $pos;
    protected GoodsReceiptService $grs;

    /** The project's migrations use a MySQL-only fulltext index — see DeliveryReceiptLinkAndBatchPoolingTest. */
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
        $this->pos = app(PurchaseOrderService::class);
        $this->grs = app(GoodsReceiptService::class);
    }

    /** @return array{0: PurchaseOrder, 1: array<int,Product>} */
    protected function purchaseOrder(array $qtys): array
    {
        $category = Category::factory()->create();
        $products = [];
        $items = [];
        foreach ($qtys as $i => $qty) {
            $generic = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'ITEM ' . $i . ' 6"']);
            $products[$i] = Product::factory()->create(['generic_name_id' => $generic->id, 'item_name' => 'ITEM ' . $i . ' 6" (BRAND)']);
            $items[] = ['generic_name_id' => $generic->id, 'qty' => $qty, 'unit' => 'PC', 'unit_cost' => 10];
        }

        $po = $this->pos->createPurchaseOrder([
            'supplier_id' => $this->supplier->id, 'order_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id, 'items' => $items,
        ]);

        return [$po->fresh('items'), $products];
    }

    protected function receive(PurchaseOrder $po, array $lines): void
    {
        $this->grs->createGoodsReceipt([
            'supplier_id' => $po->supplier_id, 'purchase_order_id' => $po->id,
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => $lines,
        ], $this->admin->id);
    }

    public function test_receiving_only_some_lines_leaves_the_rest_pending(): void
    {
        [$po, $products] = $this->purchaseOrder([20, 30, 40]);

        $this->receive($po, [
            ['product_id' => $products[0]->id, 'qty' => 5, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id],
        ]);

        $po = $po->fresh('items');
        $this->assertSame('partially_received', $po->status);
        $this->assertEquals([5, 0, 0], $po->items->pluck('received_qty')->all());
        $this->assertEquals([15, 30, 40], $po->items->map->remaining_qty->all());
    }

    public function test_closing_stops_the_pending_balance_but_keeps_what_was_received(): void
    {
        [$po, $products] = $this->purchaseOrder([20, 30]);
        $this->receive($po, [['product_id' => $products[0]->id, 'qty' => 20, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id]]);

        $unreceived = $this->pos->closeOrder($po);

        $this->assertSame('closed', $po->fresh()->status);
        $this->assertSame(30, array_sum(array_column($unreceived, 'unreceived')));
        $this->assertEquals([20, 0], $po->fresh('items')->items->pluck('received_qty')->all());
        $this->assertCount(0, $this->grs->getPendingPurchaseOrderItems($po->fresh()));
    }

    public function test_nothing_can_be_received_against_a_closed_order(): void
    {
        [$po, $products] = $this->purchaseOrder([20]);
        $this->pos->closeOrder($po);

        try {
            $this->receive($po, [['product_id' => $products[0]->id, 'qty' => 5, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id]]);
            $this->fail('a closed order accepted a receipt');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('closed', $e->errors()['items'][0]);
        }

        $this->assertSame(0, $po->items[0]->fresh()->received_qty);
        $this->assertSame('closed', $po->fresh()->status);
    }

    public function test_only_open_or_partially_received_orders_can_be_closed(): void
    {
        [$po, $products] = $this->purchaseOrder([10]);
        $this->receive($po, [['product_id' => $products[0]->id, 'qty' => 10, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id]]);
        $this->assertSame('completed', $po->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->pos->closeOrder($po->fresh());
    }

    public function test_a_draft_order_cannot_be_closed(): void
    {
        $draft = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id, 'po_no' => 'PO-DRAFT', 'status' => 'open', 'is_draft' => true,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->pos->closeOrder($draft);
    }

    public function test_reopening_restores_the_status_from_the_received_quantities(): void
    {
        [$po, $products] = $this->purchaseOrder([20, 30]);
        $this->pos->closeOrder($po);
        $this->assertSame('open', $this->pos->reopenOrder($po->fresh())->status);

        $this->receive($po->fresh(), [['product_id' => $products[0]->id, 'qty' => 5, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id]]);
        $this->pos->closeOrder($po->fresh());
        $this->assertSame('partially_received', $this->pos->reopenOrder($po->fresh())->status);

        // and receiving works again
        $this->receive($po->fresh(), [['product_id' => $products[1]->id, 'qty' => 30, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[1]->id]]);
        $this->assertSame(30, $po->items[1]->fresh()->received_qty);
    }

    public function test_only_a_closed_order_can_be_reopened(): void
    {
        [$po] = $this->purchaseOrder([10]);

        $this->expectException(ValidationException::class);
        $this->pos->reopenOrder($po);
    }

    public function test_only_a_full_admin_can_close_or_reopen_over_http(): void
    {
        [$po] = $this->purchaseOrder([10]);

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('purchase-orders.close', $po));
        $this->assertSame('open', $po->fresh()->status);
    }

    public function test_full_admin_can_close_and_reopen_over_http(): void
    {
        [$po] = $this->purchaseOrder([10]);

        $this->actingAs($this->admin)->post(route('purchase-orders.close', $po))->assertRedirect(route('purchase-orders.show', $po));
        $this->assertSame('closed', $po->fresh()->status);

        $this->actingAs($this->admin)->post(route('purchase-orders.reopen', $po))->assertRedirect(route('purchase-orders.show', $po));
        $this->assertSame('open', $po->fresh()->status);
    }

    public function test_the_pending_items_api_returns_nothing_for_a_closed_order(): void
    {
        [$po] = $this->purchaseOrder([10]);
        $this->pos->closeOrder($po);

        $this->actingAs($this->admin)
            ->getJson(route('api.purchase-orders.pending-items', $po))
            ->assertOk()
            ->assertJsonPath('items', []);
    }
}
