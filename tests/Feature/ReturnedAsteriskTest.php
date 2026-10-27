<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's idea: mark goods the client sent back with an asterisk on the Delivery Receipt and the Sales Order
 * (screen and print), with a "* Returned by client" note. The delivered quantities themselves do not change.
 */
class ReturnedAsteriskTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $product;
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

        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id, 'generic_name' => 'WOODEN HEIGHT BOARD']);
        $this->product = Product::factory()->create(['generic_name_id' => $generic->id, 'description' => 'WOODEN HEIGHT BOARD - FOLDABLE, 200CM']);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->product->id, 'qty' => 50]],
        ], $this->admin->id);

        $this->so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $generic->id, 'qty' => 19, 'price' => 100]],
        ]);
        $soItem = $this->so->items()->first();
        $batch = $this->product->batches()->first()->id;

        // The same item on three lines (17, 1, 1), like DR-2026-00004.
        $this->dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [
                ['product_batch_id' => $batch, 'qty' => 17, 'sales_order_item_id' => $soItem->id],
                ['product_batch_id' => $batch, 'qty' => 1, 'sales_order_item_id' => $soItem->id],
                ['product_batch_id' => $batch, 'qty' => 1, 'sales_order_item_id' => $soItem->id],
            ],
        ], $this->admin->id);
    }

    protected function returnItem(string $status): ReturnItem
    {
        return ReturnItem::create([
            'product_batch_id' => $this->product->batches()->first()->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id,
            'delivery_receipt_id' => $this->dr->id, 'quantity' => 2, 'return_date' => now()->toDateString(), 'reason' => 'Wrong Item Received',
            'status' => $status, 'is_draft' => false,
        ]);
    }

    public function test_nothing_is_marked_until_a_return_is_approved(): void
    {
        $this->returnItem('pending');

        $this->assertSame([], $this->dr->returnedByProduct());
        $this->assertSame([], $this->so->returnedByItem());
        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))->assertOk()->assertDontSee('Returned by Client');
    }

    public function test_an_approved_return_marks_the_dr_and_the_so(): void
    {
        $return = $this->returnItem('approved');

        $byProduct = $this->dr->returnedByProduct();
        $this->assertSame(2, $byProduct[$this->product->id]['qty']);
        $this->assertSame([$return->reference], $byProduct[$this->product->id]['refs']);

        $soItemId = $this->so->items()->first()->id;
        $this->assertSame(2, $this->so->returnedByItem()[$soItemId]['qty'], 'the three DR lines count once on the one SO line');

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))
            ->assertOk()
            ->assertSee('2 units Returned by Client')
            ->assertSee('WOODEN HEIGHT BOARD - FOLDABLE, 200CM *')
            ->assertSee($return->reference);

        $this->actingAs($this->admin)->get(route('sales-orders.show', $this->so))
            ->assertOk()
            ->assertSee('WOODEN HEIGHT BOARD (' . $this->so->items()->first()->genericName->unit . ') *', false)
            ->assertSee('2 units Returned by Client')
            ->assertSee($return->reference);
    }

    public function test_a_customer_return_adjustment_counts_too(): void
    {
        $adjustment = app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => InventoryAdjustment::CUSTOMER_RETURN,
            'delivery_receipt_id' => $this->dr->id, 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => 3]],
        ], $this->admin->id);

        $this->assertSame(3, $this->dr->returnedByProduct()[$this->product->id]['qty']);
        $this->assertSame([$adjustment->adjustment_no], $this->dr->returnedByProduct()[$this->product->id]['refs']);
    }

    public function test_the_delivered_quantities_do_not_change(): void
    {
        $this->returnItem('approved');

        $this->assertSame([17, 1, 1], $this->dr->fresh('items')->items->pluck('qty')->all());
        $this->assertSame(19, $this->so->items()->first()->delivered_qty);
    }

    public function test_goods_sent_back_cannot_be_invoiced(): void
    {
        $this->returnItem('approved');                     // 2 back, taken off the last lines first

        $dr = $this->dr->fresh();
        $this->assertSame([17, 0, 0], $dr->items->map->remaining_invoiceable_qty->all());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DeliveryReceiptService::class)->createInvoiceFromLines($dr, [$dr->items[2]->id], $this->admin->id);
    }

    public function test_a_return_that_is_still_pending_already_holds_the_goods_back_from_billing(): void
    {
        $this->returnItem('pending');

        $this->assertSame([17, 0, 0], $this->dr->fresh()->items->map->remaining_invoiceable_qty->all());
    }

    public function test_only_what_is_left_can_be_invoiced_and_a_rejected_return_frees_it(): void
    {
        $return = $this->returnItem('approved');
        $dr = $this->dr->fresh();

        $invoice = app(DeliveryReceiptService::class)->createInvoiceFromLines($dr, [$dr->items[0]->id], $this->admin->id);
        $this->assertSame(17, (int) $invoice->sales()->sum('qty'));

        $return->update(['status' => 'rejected']);
        $this->assertSame([0, 1, 1], $this->dr->fresh()->items->map->remaining_invoiceable_qty->all(), 'the 17 is invoiced; the two 1s are free again');
    }

    public function test_the_dr_page_disables_a_returned_line(): void
    {
        $this->returnItem('approved');

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))
            ->assertOk()
            ->assertSee('sent back by the customer');
    }
}
