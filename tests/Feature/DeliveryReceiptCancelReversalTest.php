<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's report (SO-2026-00002 / DR-2026-00005): he cancelled a Delivery Receipt and the Sales Order still showed everything
 * as Delivered, and the stock never came back. Cancel now puts the goods back and lowers Delivered.
 */
class DeliveryReceiptCancelReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $rope;
    protected Product $bag;
    protected Product $vest;
    protected SalesOrder $so;
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
        $this->customer = Customer::factory()->create();
        $category = Category::factory()->create();

        $make = function (string $name) use ($category) {
            $generic = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => $name]);
            $product = Product::factory()->create(['generic_name_id' => $generic->id]);
            app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'qty' => 50]],
            ], $this->admin->id);

            return [$generic, $product];
        };
        [$gRope, $this->rope] = $make('KERNMANTLE ROPE');
        [$gBag, $this->bag] = $make('ROPE STORAGE BAG');
        [$gVest, $this->vest] = $make('LIFE VEST');

        // Like SO-2026-00002: rope 2, bag 2, vest 5. The receipt was made without the vest and with the bag short (1 of 2).
        $this->so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [
                ['generic_name_id' => $gRope->id, 'qty' => 2, 'price' => 17000],
                ['generic_name_id' => $gBag->id, 'qty' => 2, 'price' => 2100],
                ['generic_name_id' => $gVest->id, 'qty' => 5, 'price' => 3525],
            ],
        ]);
        $items = $this->so->items()->orderBy('id')->get();

        $this->dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [
                ['product_batch_id' => $this->rope->batches()->first()->id, 'qty' => 2, 'sales_order_item_id' => $items[0]->id],
                ['product_batch_id' => $this->bag->batches()->first()->id, 'qty' => 1, 'sales_order_item_id' => $items[1]->id],
            ],
        ], $this->admin->id);
    }

    protected function warehouse(Product $product): int
    {
        return (int) $product->batches()->first()->fresh()->qtyAtLocation(Location::warehouse()->id);
    }

    protected function delivered(): array
    {
        return $this->so->items()->orderBy('id')->pluck('delivered_qty')->all();
    }

    public function test_cancelling_puts_the_goods_back_and_lowers_delivered(): void
    {
        $this->assertSame([2, 1, 0], $this->delivered());
        $this->assertSame([48, 49], [$this->warehouse($this->rope), $this->warehouse($this->bag)]);
        $this->assertSame('partially_delivered', $this->so->fresh()->status);

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr))
            ->assertRedirect(route('delivery-receipts.show', $this->dr));

        $this->assertSame([0, 0, 0], $this->delivered(), 'back to where it was before the receipt');
        $this->assertSame([50, 50], [$this->warehouse($this->rope), $this->warehouse($this->bag)]);
        $this->assertSame('open', $this->so->fresh()->status);

        $dr = $this->dr->fresh();
        $this->assertTrue($dr->isCancelled());
        $this->assertNotNull($dr->stock_reversed_at);
        $this->assertSame(2, $dr->items()->count(), 'the lines stay as the receipt\'s history');
        $this->assertDatabaseHas('activity_logs', ['module' => 'DeliveryReceipt', 'action' => 'cancelled', 'loggable_id' => $dr->id]);
    }

    public function test_the_order_can_be_delivered_again_after_the_cancel(): void
    {
        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $items = $this->so->items()->orderBy('id')->get();
        $again = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->rope->batches()->first()->id, 'qty' => 2, 'sales_order_item_id' => $items[0]->id]],
        ], $this->admin->id);

        $this->assertNotNull($again);
        $this->assertSame([2, 0, 0], $this->delivered());
    }

    public function test_a_delivered_receipt_cannot_be_cancelled_and_nothing_changes(): void
    {
        $this->dr->update(['status' => 'delivered']);

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $this->assertSame('delivered', $this->dr->fresh()->status);
        $this->assertSame([2, 1, 0], $this->delivered());
        $this->assertSame(48, $this->warehouse($this->rope));
    }

    public function test_an_invoiced_receipt_cannot_be_cancelled(): void
    {
        $dr = $this->dr->fresh('items');
        app(DeliveryReceiptService::class)->createInvoiceFromLines($dr, [$dr->items[0]->id], $this->admin->id);

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $this->assertFalse($this->dr->fresh()->isCancelled());
        $this->assertSame([2, 1, 0], $this->delivered());
    }

    public function test_a_receipt_with_returns_cannot_be_cancelled(): void
    {
        ReturnItem::create([
            'product_batch_id' => $this->rope->batches()->first()->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id,
            'delivery_receipt_id' => $this->dr->id, 'quantity' => 1, 'return_date' => now()->toDateString(), 'reason' => 'Changed Mind',
            'status' => 'pending', 'is_draft' => false,
        ]);

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $this->assertFalse($this->dr->fresh()->isCancelled());
        $this->assertSame(48, $this->warehouse($this->rope));
    }

    public function test_an_advance_order_receipt_just_puts_the_stock_back(): void
    {
        $advance = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'advance_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->vest->batches()->first()->id, 'qty' => 5]],
        ], $this->admin->id);
        $this->assertSame(45, $this->warehouse($this->vest));

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $advance));

        $this->assertSame(50, $this->warehouse($this->vest));
        $this->assertTrue($advance->fresh()->isCancelled());
    }

    public function test_only_a_full_admin_can_cancel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('delivery-receipts.cancel', $this->dr));

        $this->assertFalse($this->dr->fresh()->isCancelled());
        $this->assertSame([2, 1, 0], $this->delivered());
    }

    public function test_the_stock_movement_is_recorded_against_the_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $in = StockMovement::where('source_type', DeliveryReceipt::class)->where('source_id', $this->dr->id)->where('type', 'in')->get();
        $this->assertSame([1, 2], $in->pluck('quantity')->sort()->values()->all());
    }

    public function test_a_cancelled_receipts_lines_no_longer_lock_the_order_for_editing(): void
    {
        $service = app(SalesOrderService::class);
        $items = $this->so->items()->orderBy('id')->get();
        $this->assertTrue($service->lineHasDeliveries($items[0]));

        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $this->assertFalse($service->lineHasDeliveries($items[0]->fresh()), 'the receipt is void, so the line is free again');
    }

    // ---- repair for a receipt that was cancelled while Cancel only changed the status

    protected function cancelTheOldWay(): void
    {
        $this->dr->update(['status' => 'cancelled']);
    }

    public function test_the_repair_command_shows_first_and_changes_nothing_without_apply(): void
    {
        $this->cancelTheOldWay();

        $this->assertSame(0, Artisan::call('dr:reverse-cancelled', ['dr_no' => $this->dr->dr_no]));

        $this->assertStringContainsString('Nothing changed', Artisan::output());
        $this->assertSame([2, 1, 0], $this->delivered());
        $this->assertSame(48, $this->warehouse($this->rope));
        $this->assertNull($this->dr->fresh()->stock_reversed_at);
    }

    public function test_the_repair_command_puts_everything_back_once(): void
    {
        $this->cancelTheOldWay();

        $this->assertSame(0, Artisan::call('dr:reverse-cancelled', ['dr_no' => $this->dr->dr_no, '--apply' => true]));

        $this->assertSame([0, 0, 0], $this->delivered());
        $this->assertSame([50, 50], [$this->warehouse($this->rope), $this->warehouse($this->bag)]);
        $this->assertSame('open', $this->so->fresh()->status);
        $this->assertNotNull($this->dr->fresh()->stock_reversed_at);

        // run again: nothing more happens
        Artisan::call('dr:reverse-cancelled', ['dr_no' => $this->dr->dr_no, '--apply' => true]);
        $this->assertStringContainsString('already put back', Artisan::output());
        $this->assertSame([50, 50], [$this->warehouse($this->rope), $this->warehouse($this->bag)]);
    }

    public function test_the_repair_command_refuses_a_receipt_that_is_not_cancelled(): void
    {
        $this->assertSame(1, Artisan::call('dr:reverse-cancelled', ['dr_no' => $this->dr->dr_no, '--apply' => true]));
        $this->assertSame([2, 1, 0], $this->delivered());
    }

    public function test_a_cancel_that_already_reversed_cannot_be_reversed_again(): void
    {
        $this->actingAs($this->admin)->post(route('delivery-receipts.cancel', $this->dr));

        $this->expectException(ValidationException::class);
        app(DeliveryReceiptService::class)->reverseCancelledDeliveryReceipt($this->dr->fresh(), $this->admin->id);
    }
}
