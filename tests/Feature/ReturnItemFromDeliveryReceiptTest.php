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

    // ---- the form that lists every line of the receipt (Return from a Delivery Receipt)

    protected function secondProduct(): Product
    {
        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id]);
        $product = Product::factory()->create(['generic_name_id' => $generic->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $product->id, 'qty' => 20]],
        ], $this->admin->id);

        return $product;
    }

    protected function twoLineReceipt(): array
    {
        $other = $this->secondProduct();
        $dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'walk_in', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [
                ['product_batch_id' => $this->batch()->id, 'qty' => 4],
                ['product_batch_id' => $other->batches()->first()->id, 'qty' => 3],
            ],
        ], $this->admin->id);

        return [$dr, $other];
    }

    public function test_the_api_lists_the_receipts_lines_with_what_can_still_be_returned(): void
    {
        $this->submitReturn(['quantity' => 4]);   // 4 of the 10 on $this->dr, still pending

        $deliveries = $this->actingAs($this->admin)->getJson("/api/customers/{$this->customer->id}/returnable-deliveries")->assertOk()->json('deliveries');

        $this->assertCount(1, $deliveries);
        $row = $deliveries[0]['rows'][0];
        $this->assertSame([10, 4, 6], [$row['delivered'], $row['returned'], $row['returnable']]);
        $this->assertSame($this->batch()->id, $row['batch_id']);
        $this->assertFalse($deliveries[0]['invoiced']);
    }

    public function test_a_receipt_with_nothing_left_is_not_offered(): void
    {
        $this->submitReturn(['quantity' => 10]);

        $this->actingAs($this->admin)->getJson("/api/customers/{$this->customer->id}/returnable-deliveries")
            ->assertOk()->assertJsonCount(0, 'deliveries');
    }

    public function test_one_save_returns_several_lines_each_with_its_own_reason(): void
    {
        [$dr, $other] = $this->twoLineReceipt();

        $this->actingAs($this->admin)->post(route('return-items.store-from-dr'), [
            'delivery_receipt_id' => $dr->id, 'return_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'lines' => [
                $this->batch()->id => ['qty' => 2, 'reason' => 'Damaged During Delivery', 'notes' => 'cracked'],
                $other->batches()->first()->id => ['qty' => 1, 'reason' => 'Wrong Item Received'],
            ],
        ])->assertRedirect(route('return-items.index'));

        $returns = ReturnItem::where('delivery_receipt_id', $dr->id)->get();
        $this->assertCount(2, $returns);
        $this->assertSame(['Damaged During Delivery', 'Wrong Item Received'], $returns->sortBy('id')->pluck('reason')->all());
        $this->assertSame([2, 1], $returns->sortBy('id')->pluck('quantity')->all());
        $this->assertTrue($returns->every(fn ($r) => $r->status === 'pending' && ! $r->is_draft && (int) $r->customer_id === $this->customer->id));
    }

    public function test_lines_left_at_zero_are_skipped_and_a_return_needs_one_quantity(): void
    {
        [$dr, $other] = $this->twoLineReceipt();

        $this->actingAs($this->admin)->post(route('return-items.store-from-dr'), [
            'delivery_receipt_id' => $dr->id, 'return_date' => now()->toDateString(),
            'lines' => [
                $this->batch()->id => ['qty' => 0, 'reason' => ''],
                $other->batches()->first()->id => ['qty' => 0, 'reason' => ''],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, ReturnItem::where('delivery_receipt_id', $dr->id)->count());
    }

    public function test_every_returned_line_needs_a_reason(): void
    {
        [$dr] = $this->twoLineReceipt();

        $this->actingAs($this->admin)->post(route('return-items.store-from-dr'), [
            'delivery_receipt_id' => $dr->id, 'return_date' => now()->toDateString(),
            'lines' => [$this->batch()->id => ['qty' => 1, 'reason' => '']],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, ReturnItem::where('delivery_receipt_id', $dr->id)->count());
    }

    public function test_it_is_all_or_nothing_when_one_line_asks_for_too_much(): void
    {
        [$dr, $other] = $this->twoLineReceipt();

        $this->actingAs($this->admin)->post(route('return-items.store-from-dr'), [
            'delivery_receipt_id' => $dr->id, 'return_date' => now()->toDateString(),
            'lines' => [
                $this->batch()->id => ['qty' => 2, 'reason' => 'Changed Mind'],
                $other->batches()->first()->id => ['qty' => 9, 'reason' => 'Changed Mind'],   // only 3 were delivered
            ],
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, ReturnItem::where('delivery_receipt_id', $dr->id)->count(), 'the valid line is not kept either');
    }

    public function test_only_a_full_admin_can_use_the_form_and_the_page_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('return-items.store-from-dr'), [
                'delivery_receipt_id' => $this->dr->id, 'return_date' => now()->toDateString(),
                'lines' => [$this->batch()->id => ['qty' => 1, 'reason' => 'Changed Mind']],
            ]);
        $this->assertSame(0, ReturnItem::count());

        $this->actingAs($this->admin)->get(route('return-items.from-dr', ['delivery_receipt_id' => $this->dr->id]))
            ->assertOk()->assertSee('Return from a Delivery Receipt')->assertSee('SAGADA LOCAL GOVERNMENT UNIT');
    }
}
