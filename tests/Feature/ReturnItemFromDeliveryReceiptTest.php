<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's request: Return Items must not depend on an Invoice. A customer can send goods back when only the
 * Sales Order / Delivery Receipt exist; the return points at that DR, the stock goes back to the
 * Warehouse, and nothing is refunded for goods that were never billed.
 */
class ReturnItemFromDeliveryReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $product;
    protected DeliveryReceipt $dr;

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
        $this->customer = Customer::factory()->create(['customer_name' => 'SAGADA LOCAL GOVERNMENT UNIT']);

        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id]);
        $this->product = Product::factory()->create(['generic_name_id' => $generic->id, 'unit_price' => 100]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->product->id, 'qty' => 50]],
        ], $this->admin->id);

        $this->dr = $this->deliver(10);
    }

    protected function deliver(int $qty): DeliveryReceipt
    {
        return app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'walk_in', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->batch()->id, 'qty' => $qty]],
        ], $this->admin->id);
    }

    protected function batch()
    {
        return $this->product->batches()->first();
    }

    protected function qtyAt(Location $location): int
    {
        return (int) $this->batch()->fresh()->qtyAtLocation($location->id);
    }

    protected function submitReturn(array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('return-items.store'), array_merge([
            'save_action' => 'posted', 'customer_id' => $this->customer->id, 'delivery_receipt_id' => $this->dr->id,
            'product_batch_id' => $this->batch()->id, 'quantity' => 2, 'return_date' => now()->toDateString(), 'reason' => 'Wrong Item Received',
        ], $extra));
    }

    public function test_an_item_delivered_without_an_invoice_is_offered_for_return(): void
    {
        $items = $this->actingAs($this->admin)->getJson("/api/customers/{$this->customer->id}/purchased-items")->assertOk()->json('items');

        $this->assertCount(1, $items);
        $this->assertSame(10, $items[0]['total_purchased']);
        $this->assertSame(10, $items[0]['returnable']);
        $this->assertSame($this->dr->dr_no, $items[0]['sources'][0]['dr_no']);
        $this->assertSame(0, $items[0]['sources'][0]['invoiced']);
    }

    public function test_a_return_names_its_delivery_receipt_and_takes_the_customer_from_it(): void
    {
        $this->submitReturn(['customer_id' => ''])->assertRedirect();

        $return = ReturnItem::firstOrFail();
        $this->assertSame($this->dr->id, $return->delivery_receipt_id);
        $this->assertSame($this->customer->id, $return->customer_id);
    }

    public function test_it_cannot_return_more_than_the_dr_delivered_minus_what_is_already_returned(): void
    {
        $this->submitReturn(['quantity' => 11])->assertSessionHasErrors('quantity');
        $this->assertSame(0, ReturnItem::count());

        $this->submitReturn(['quantity' => 8])->assertSessionHasNoErrors();
        $this->submitReturn(['quantity' => 3])->assertSessionHasErrors('quantity');   // only 2 left
        $this->assertSame(1, ReturnItem::count());
    }

    public function test_a_dr_of_another_customer_or_one_that_never_delivered_the_item_is_refused(): void
    {
        $other = Customer::factory()->create();
        $this->submitReturn(['customer_id' => $other->id])->assertSessionHasErrors('delivery_receipt_id');

        $otherProduct = Product::factory()->create(['generic_name_id' => $this->product->generic_id ?? $this->product->generic_name_id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $otherProduct->id, 'qty' => 5]],
        ], $this->admin->id);
        $this->submitReturn(['product_batch_id' => $otherProduct->batches()->first()->id])->assertSessionHasErrors('delivery_receipt_id');

        $this->assertSame(0, ReturnItem::count());
    }

    public function test_approving_a_return_from_an_uninvoiced_dr_restocks_the_warehouse_and_refunds_nothing(): void
    {
        $warehouse = Location::warehouse();
        $pos = Location::pos();
        $this->assertSame(40, $this->qtyAt($warehouse));

        $this->submitReturn();
        $return = ReturnItem::firstOrFail();
        $this->assertFalse($return->isRefundable());

        // Even if a cash refund is sent, a never-invoiced delivery is not refunded.
        $this->actingAs($this->admin)->post(route('return-items.approve', $return), ['refund_method' => 'cash', 'stock_disposition' => 'sellable']);

        $return = $return->fresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame('none', $return->refund_method);
        $this->assertEquals(0, $return->refund_amount);
        $this->assertSame(42, $this->qtyAt($warehouse), 'goes back where the DR took it from');
        $this->assertSame(0, $this->qtyAt($pos));
        $this->assertSame(0, $this->customer->payments()->count(), 'no credit recorded');

        $movement = StockMovement::where('source_type', ReturnItem::class)->firstOrFail();
        $this->assertSame($return->id, (int) $movement->source_id, 'Product History can show the Return Item');
    }

    public function test_a_return_from_an_invoiced_dr_is_refunded_as_before(): void
    {
        app(DeliveryReceiptService::class)->createInvoiceFromLines($this->dr->fresh('items'), $this->dr->items->pluck('id')->all(), $this->admin->id);

        $this->submitReturn();
        $return = ReturnItem::firstOrFail();
        $this->assertTrue($return->isRefundable());

        $this->actingAs($this->admin)->post(route('return-items.approve', $return), ['refund_method' => 'none', 'stock_disposition' => 'sellable'])
            ->assertSessionHas('error');
        $this->assertSame('pending', $return->fresh()->status);

        $this->actingAs($this->admin)->post(route('return-items.approve', $return), ['refund_method' => 'cash', 'stock_disposition' => 'sellable']);
        $this->assertSame('cash', $return->fresh()->refund_method);
        $this->assertGreaterThan(0, (float) $return->fresh()->refund_amount);
    }

    public function test_a_return_without_a_dr_still_goes_back_to_pos(): void
    {
        $this->submitReturn(['delivery_receipt_id' => '']);
        $return = ReturnItem::firstOrFail();
        $this->assertNull($return->delivery_receipt_id);

        $this->actingAs($this->admin)->post(route('return-items.approve', $return), ['refund_method' => 'cash', 'stock_disposition' => 'sellable']);

        $this->assertSame(2, $this->qtyAt(Location::pos()));
    }

    public function test_product_history_shows_the_reference_and_the_dr_the_goods_came_from(): void
    {
        $this->submitReturn();
        $return = ReturnItem::firstOrFail();
        $this->actingAs($this->admin)->post(route('return-items.approve', $return), ['refund_method' => 'cash', 'stock_disposition' => 'sellable']);

        $this->actingAs($this->admin)->get(route('inventory-items.index', ['tab' => 'history', 'product_id' => $this->product->id]))
            ->assertOk()
            ->assertSee('Reference')
            ->assertSee($return->fresh()->reference)
            ->assertSee('Return Item')
            ->assertSee($this->dr->dr_no)
            ->assertSee(route('delivery-receipts.show', $this->dr), false);

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))
            ->assertOk()->assertSee('Returned by the customer')->assertSee($return->reference);
    }
}
