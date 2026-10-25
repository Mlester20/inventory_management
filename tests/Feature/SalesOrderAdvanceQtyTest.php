<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\SalesOrderService;
use App\Services\SalesOrderSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's case (SO-2026-00003): Advance Qty was already delivered in advance and will never get a DR,
 * so it must not count as Remaining — otherwise the order can never reach 0 / Completed.
 */
class SalesOrderAdvanceQtyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;

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
    }

    /** @return array{0: GenericName, 1: Product} A stocked item (20 in the Warehouse). */
    protected function stockedItem(string $name): array
    {
        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id, 'generic_name' => $name . ' ' . uniqid()]);
        $product = Product::factory()->create(['generic_name_id' => $generic->id, 'brand_name' => $name]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $product->id, 'qty' => 20]],
        ], $this->admin->id);

        return [$generic, $product];
    }

    /** Sir's sample: lines 1 and 2 fully delivered in advance, line 3 a normal order of 4. */
    protected function sirsOrder(): array
    {
        [$gA] = $this->stockedItem('ADV A');
        [$gB] = $this->stockedItem('ADV B');
        [$gC, $pC] = $this->stockedItem('NORMAL C');

        $so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [
                ['generic_name_id' => $gA->id, 'qty' => 8, 'price' => 100, 'advance_order_qty' => 8],
                ['generic_name_id' => $gB->id, 'qty' => 1, 'price' => 100, 'advance_order_qty' => 1],
                ['generic_name_id' => $gC->id, 'qty' => 4, 'price' => 100, 'advance_order_qty' => 0],
            ],
        ]);

        return [$so->fresh('items'), $pC];
    }

    public function test_advance_qty_is_not_remaining(): void
    {
        [$so] = $this->sirsOrder();

        $this->assertSame([0, 0, 4], $so->items->map->remaining_qty->all());
        $this->assertSame('open', $so->status);
    }

    public function test_partly_advance_line_leaves_only_the_rest(): void
    {
        [$generic] = $this->stockedItem('PART');
        $so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $generic->id, 'qty' => 10, 'price' => 100, 'advance_order_qty' => 4]],
        ]);

        $this->assertSame(6, $so->items()->first()->remaining_qty);
    }

    public function test_delivering_the_non_advance_lines_completes_the_order(): void
    {
        [$so, $pC] = $this->sirsOrder();

        $dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $pC->batches()->first()->id, 'qty' => 4, 'sales_order_item_id' => $so->items[2]->id]],
        ], $this->admin->id);

        $so = $so->fresh('items');
        $this->assertSame('completed', $so->status);
        $this->assertSame([0, 0, 0], $so->items->map->remaining_qty->all());
        $this->assertSame([0, 0, 4], $so->items->pluck('delivered_qty')->all(), 'advance stays out of delivered_qty');
        $this->assertNotNull($dr);
    }

    public function test_a_dr_cannot_deliver_the_advance_part_again(): void
    {
        [$gA, $pA] = $this->stockedItem('ADV ONLY');
        $so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $gA->id, 'qty' => 10, 'price' => 100, 'advance_order_qty' => 4]],
        ]);
        $item = $so->items()->first();

        try {
            app(DeliveryReceiptService::class)->createDeliveryReceipt([
                'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
                'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
                'items' => [['product_batch_id' => $pA->batches()->first()->id, 'qty' => 7, 'sales_order_item_id' => $item->id]],
            ], $this->admin->id);
            $this->fail('delivered more than the non-advance remainder');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('remaining balance', $e->errors()['items'][0]);
        }

        $this->assertSame(0, $item->fresh()->delivered_qty);
        $this->assertSame(20, (int) $pA->fresh('batches')->batches->sum(fn ($b) => $b->qtyAtLocation(Location::warehouse()->id)));
    }

    public function test_an_order_that_is_entirely_advance_is_completed_straight_away(): void
    {
        [$generic] = $this->stockedItem('ALL ADV');

        $so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $generic->id, 'qty' => 5, 'price' => 100, 'advance_order_qty' => 5]],
        ]);

        $this->assertSame('completed', $so->fresh()->status);
    }

    public function test_the_summary_does_not_call_advance_qty_undelivered(): void
    {
        [$so] = $this->sirsOrder();

        $summary = app(SalesOrderSummaryService::class)->customerSummary()->firstWhere('customer_id', $this->customer->id);

        $this->assertSame(13, $summary['qty_ordered']);
        $this->assertSame(4, $summary['undelivered']);

        $lines = app(SalesOrderSummaryService::class)->items(SalesOrderSummaryService::SCOPE_UNDELIVERED, $this->customer->id);
        $this->assertSame(1, $lines->flatMap(fn ($c) => $c['items'])->count(), 'only the normal line is still undelivered');
        $this->assertNotNull($so);
    }

    public function test_close_so_stops_further_delivery_and_reopen_restores_the_status(): void
    {
        [$so, $pC] = $this->sirsOrder();
        $service = app(SalesOrderService::class);

        $undelivered = $service->closeOrder($so);
        $this->assertSame([4], array_column($undelivered, 'undelivered'), 'only the line that was really short is reported');
        $this->assertSame('closed', $so->fresh()->status);

        try {
            app(DeliveryReceiptService::class)->createDeliveryReceipt([
                'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
                'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
                'items' => [['product_batch_id' => $pC->batches()->first()->id, 'qty' => 1, 'sales_order_item_id' => $so->items[2]->id]],
            ], $this->admin->id);
            $this->fail('delivered against a closed Sales Order');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('closed', $e->errors()['items'][0]);
        }

        $service->reopenOrder($so);
        $this->assertSame('open', $so->fresh()->status);
    }

    public function test_only_open_orders_close_and_only_closed_orders_reopen(): void
    {
        [$so] = $this->sirsOrder();
        $service = app(SalesOrderService::class);

        try {
            $service->reopenOrder($so);
            $this->fail('reopened an order that is not closed');
        } catch (ValidationException) {
        }

        $service->closeOrder($so);
        try {
            $service->closeOrder($so);
            $this->fail('closed an order twice');
        } catch (ValidationException) {
        }

        $done = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $this->stockedItem('DONE')[0]->id, 'qty' => 2, 'price' => 100, 'advance_order_qty' => 2]],
        ]);
        $this->assertSame('completed', $done->fresh()->status);
        try {
            $service->closeOrder($done);
            $this->fail('closed a completed order');
        } catch (ValidationException) {
        }
    }

    public function test_revising_a_dr_does_not_reopen_a_closed_order(): void
    {
        [$so, $pC] = $this->sirsOrder();
        $dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $pC->batches()->first()->id, 'qty' => 3, 'sales_order_item_id' => $so->items[2]->id]],
        ], $this->admin->id);
        app(SalesOrderService::class)->closeOrder($so->fresh());

        app(DeliveryReceiptService::class)->reviseDeliveryReceipt($dr->fresh('items'), [$dr->items[0]->id => 1], $this->admin->id);

        $this->assertSame('closed', $so->fresh()->status);
    }

    public function test_a_closed_order_is_counted_apart_in_the_summary(): void
    {
        [$so] = $this->sirsOrder();
        app(SalesOrderService::class)->closeOrder($so);

        $summary = app(SalesOrderSummaryService::class)->customerSummary()->firstWhere('customer_id', $this->customer->id);
        $this->assertSame(1, $summary['closed']);
        $this->assertSame(0, $summary['open']);
        $this->assertSame(0, $summary['undelivered'], 'a closed order owes nothing');
        $this->assertSame('CLOSED', SalesOrderSummaryService::statusLabel($so->fresh()));
    }

    public function test_only_a_full_admin_can_close_over_http(): void
    {
        [$so] = $this->sirsOrder();

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('sales-orders.close', $so));
        $this->assertSame('open', $so->fresh()->status);

        $this->actingAs($this->admin)->post(route('sales-orders.close', $so))->assertRedirect(route('sales-orders.show', $so));
        $this->assertSame('closed', $so->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['module' => 'SalesOrder', 'action' => 'closed', 'loggable_id' => $so->id]);

        $this->actingAs($this->admin)->post(route('sales-orders.reopen', $so));
        $this->assertSame('open', $so->fresh()->status);
    }
}
