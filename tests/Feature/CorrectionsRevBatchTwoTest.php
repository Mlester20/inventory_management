<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\GoodsReceipt;
use App\Models\InventoryAdjustment;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\GoodsReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\PurchaseOrderService;
use App\Services\ReturnItemService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's CORRECTIONS_REV ticket, the parts he answered: a Sales Order whose delivery was returned goes back to Open
 * (Delivered and Remaining stay as they were), one invoice for everything a closed Sales Order delivered, and
 * editing a posted Goods Receipt (the stock follows, and cannot go negative).
 */
class CorrectionsRevBatchTwoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Location $warehouse;

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
        $this->warehouse = Location::warehouse();
    }

    protected function product(string $name, int $stock = 0): Product
    {
        $generic = GenericName::factory()->create(['category_id' => Category::first()?->id ?? Category::factory()->create()->id, 'generic_name' => $name . ' ' . uniqid(), 'unit' => 'BOX']);
        $product = Product::factory()->create(['generic_name_id' => $generic->id, 'brand_name' => 'B', 'description' => $name . ', 5CM']);

        if ($stock > 0) {
            app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'qty' => $stock]],
            ], $this->admin->id);
        }

        return $product;
    }

    protected function stock(Product $product): int
    {
        return (int) $product->fresh('batches')->batches->sum(fn ($batch) => $batch->qtyAtLocation($this->warehouse->id));
    }

    /** An order with one line per [product, qty, price], delivered in full by one Delivery Receipt. */
    protected function deliveredOrder(array $lines): array
    {
        $order = SalesOrder::create([
            'customer_id' => $this->customer->id, 'so_no' => 'SO-CB-' . (SalesOrder::count() + 1), 'status' => 'open', 'is_draft' => false,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'po_no' => 'PO-CB',
        ]);

        $dr = [];
        foreach ($lines as [$product, $qty, $price]) {
            $item = $order->items()->create(['generic_name_id' => $product->generic_name_id, 'product_id' => $product->id, 'qty' => $qty, 'price' => $price, 'delivered_qty' => 0]);
            $dr[] = ['product_batch_id' => $product->batches()->first()->id, 'qty' => $qty, 'sales_order_item_id' => $item->id];
        }

        $receipt = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $order->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'items' => $dr,
        ], $this->admin->id);

        return [$order->fresh('items'), $receipt->fresh('items')];
    }

    protected function pendingReturn(DeliveryReceipt $receipt, Product $product, int $qty): ReturnItem
    {
        return ReturnItem::create([
            'product_batch_id' => $product->batches()->first()->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id,
            'delivery_receipt_id' => $receipt->id, 'quantity' => $qty, 'return_date' => now()->toDateString(), 'reason' => 'Wrong Item Received',
            'status' => 'pending', 'is_draft' => false,
        ]);
    }

    // ---- #4: a returned delivery reopens the Sales Order

    public function test_approving_a_return_of_everything_delivered_puts_the_order_back_to_open(): void
    {
        $a = $this->product('ITEM A', 20);
        [$order, $receipt] = $this->deliveredOrder([[$a, 19, 100]]);
        $this->assertSame('completed', $order->status);

        $return = $this->pendingReturn($receipt, $a, 19);
        $this->assertSame('completed', $order->fresh()->status, 'a pending return changes nothing yet');

        app(ReturnItemService::class)->approve($return, 'none', 'restock', $this->admin->id);

        $order = $order->fresh('items');
        $this->assertSame('open', $order->status);
        $this->assertSame([19, 0], [$order->items[0]->delivered_qty, $order->items[0]->remaining_qty], 'Delivered keeps 19 and Remaining stays 0');
    }

    public function test_a_partial_return_or_a_return_of_one_line_makes_it_partially_delivered(): void
    {
        $a = $this->product('ITEM A', 20);
        $b = $this->product('ITEM B', 20);
        [$order, $receipt] = $this->deliveredOrder([[$a, 10, 100], [$b, 5, 50]]);

        app(ReturnItemService::class)->approve($this->pendingReturn($receipt, $a, 3), 'none', 'restock', $this->admin->id);
        $this->assertSame('partially_delivered', $order->fresh()->status, '7 of 10 is left delivered');

        app(ReturnItemService::class)->approve($this->pendingReturn($receipt, $a, 7), 'none', 'restock', $this->admin->id);
        $this->assertSame('partially_delivered', $order->fresh()->status, 'line B is still delivered');
    }

    public function test_a_closed_order_stays_closed_and_a_rejected_return_changes_nothing(): void
    {
        $a = $this->product('ITEM A', 20);
        [$order, $receipt] = $this->deliveredOrder([[$a, 10, 100]]);

        $rejected = $this->pendingReturn($receipt, $a, 4);
        $rejected->update(['status' => 'rejected']);
        $order->syncStatus();
        $this->assertSame('completed', $order->fresh()->status);

        $order->update(['status' => 'closed']);
        app(ReturnItemService::class)->approve($this->pendingReturn($receipt, $a, 4), 'none', 'restock', $this->admin->id);
        $this->assertSame('closed', $order->fresh()->status);
    }

    public function test_a_customer_return_adjustment_also_reopens_the_order(): void
    {
        $a = $this->product('ITEM A', 20);
        [$order, $receipt] = $this->deliveredOrder([[$a, 10, 100]]);

        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => InventoryAdjustment::CUSTOMER_RETURN,
            'delivery_receipt_id' => $receipt->id, 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $a->id, 'product_batch_id' => $a->batches()->first()->id, 'qty' => 10]],
        ], $this->admin->id);

        $this->assertSame('open', $order->fresh()->status);
    }

    // ---- #5: one invoice from a closed Sales Order

    public function test_one_invoice_covers_the_lines_of_all_the_orders_receipts(): void
    {
        $a = $this->product('ITEM A', 50);
        $b = $this->product('ITEM B', 50);
        [$order, $first] = $this->deliveredOrder([[$a, 10, 100]]);
        $itemB = $order->items()->create(['generic_name_id' => $b->generic_name_id, 'product_id' => $b->id, 'qty' => 6, 'price' => 50, 'delivered_qty' => 0]);
        $second = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $order->id, 'transaction_type' => 'purchase_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id, 'items' => [['product_batch_id' => $b->batches()->first()->id, 'qty' => 6, 'sales_order_item_id' => $itemB->id]],
        ], $this->admin->id);
        $order->update(['status' => 'closed']);

        $invoice = app(DeliveryReceiptService::class)->createInvoiceFromSalesOrder($order->fresh(), $this->admin->id);

        $this->assertSame(2, $invoice->sales()->count(), 'both receipts on the one invoice');
        $this->assertEquals(10 * 100 + 6 * 50, $invoice->total_sales);
        $this->assertSame('PO-CB', $invoice->po_no);
        $this->assertSame([0, 0], [$first->items()->first()->remaining_invoiceable_qty, $second->items()->first()->remaining_invoiceable_qty]);
    }

    public function test_the_invoice_leaves_out_what_was_returned_cancelled_or_already_invoiced(): void
    {
        $a = $this->product('ITEM A', 50);
        $b = $this->product('ITEM B', 50);
        [$order, $first] = $this->deliveredOrder([[$a, 10, 100]]);
        $itemB = $order->items()->create(['generic_name_id' => $b->generic_name_id, 'product_id' => $b->id, 'qty' => 6, 'price' => 50, 'delivered_qty' => 0]);
        $service = app(DeliveryReceiptService::class);
        $second = $service->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $order->id, 'transaction_type' => 'purchase_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id, 'items' => [['product_batch_id' => $b->batches()->first()->id, 'qty' => 4, 'sales_order_item_id' => $itemB->id]],
        ], $this->admin->id);
        $third = $service->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $order->id, 'transaction_type' => 'purchase_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id, 'items' => [['product_batch_id' => $b->batches()->first()->id, 'qty' => 2, 'sales_order_item_id' => $itemB->id]],
        ], $this->admin->id);

        $service->cancelDeliveryReceipt($third, $this->admin->id);                                         // cancelled: left out
        app(ReturnItemService::class)->approve($this->pendingReturn($first, $a, 3), 'none', 'restock', $this->admin->id);   // 3 of A sent back
        $service->createInvoiceFromLines($second->fresh(), [$second->items()->first()->id], $this->admin->id);              // B already invoiced
        $order->fresh()->update(['status' => 'closed']);

        $invoice = $service->createInvoiceFromSalesOrder($order->fresh(), $this->admin->id);

        $this->assertSame(1, $invoice->sales()->count());
        $this->assertSame(7, (int) $invoice->sales()->first()->qty, '10 delivered minus 3 sent back');

        $this->expectException(ValidationException::class);
        $service->createInvoiceFromSalesOrder($order->fresh(), $this->admin->id);   // nothing left now
    }

    public function test_only_a_full_admin_can_invoice_and_only_a_closed_order(): void
    {
        $a = $this->product('ITEM A', 50);
        [$order] = $this->deliveredOrder([[$a, 10, 100]]);
        $route = route('sales-orders.create-invoice', $order);

        $this->actingAs($this->admin)->post($route)->assertRedirect(route('sales-orders.show', $order));   // not closed yet
        $this->assertSame(0, \App\Models\Invoice::count());

        $order->update(['status' => 'closed']);

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))->post($route);
        $this->assertSame(0, \App\Models\Invoice::count());

        $invoice = null;
        $this->actingAs($this->admin)->get(route('sales-orders.show', $order))->assertOk()->assertSee('Create Invoice');
        $this->actingAs($this->admin)->post($route, ['po_no' => 'BOGH 1-26'])->assertRedirect();
        $invoice = \App\Models\Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame('BOGH 1-26', $invoice->po_no);
        $this->assertDatabaseHas('activity_logs', ['module' => 'SalesOrder', 'action' => 'invoice_created', 'loggable_id' => $order->id]);
    }

    // ---- #7: editing a posted Goods Receipt

    protected function receipt(Product $product, int $qty = 10, string $lot = 'LOT-A', ?string $expiry = '2028-01-31', float $cost = 5): GoodsReceipt
    {
        return app(GoodsReceiptService::class)->createGoodsReceipt([
            'supplier_id' => Supplier::factory()->create()->id, 'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $cost, 'batch_no' => $lot, 'expiration_date' => $expiry]],
        ], $this->admin->id)->fresh('items');
    }

    protected function revise(GoodsReceipt $receipt, array $line, array $header = []): array
    {
        return app(GoodsReceiptService::class)->reviseGoodsReceipt(
            $receipt, array_merge(['receipt_date' => $receipt->receipt_date->toDateString()], $header), [$receipt->items[0]->id => $line], $this->admin->id
        );
    }

    public function test_a_quantity_change_moves_the_warehouse_stock_by_the_difference(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10);

        $this->revise($receipt, ['qty' => 14]);
        $this->assertSame(14, $this->stock($p));
        $this->assertSame(14, $receipt->fresh('items')->items[0]->qty);

        $this->revise($receipt->fresh('items'), ['qty' => 9]);
        $this->assertSame(9, $this->stock($p));

        $moves = StockMovement::where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->orderBy('id')->pluck('quantity')->all();
        $this->assertSame([10, 4, -5], array_map('intval', $moves), 'every change is a movement of this receipt, so Product History shows it');
    }

    public function test_the_quantity_cannot_be_lowered_below_what_is_still_in_the_warehouse(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10);
        $this->deliveredOrder([[$p, 8, 100]]);   // 8 of the 10 went out on a delivery

        try {
            $this->revise($receipt, ['qty' => 5]);   // would need to take 5 out, only 2 are left
            $this->fail('stock was taken out that is no longer there');
        } catch (ValidationException) {
        }

        $this->assertSame(2, $this->stock($p));
        $this->assertSame(10, $receipt->fresh('items')->items[0]->qty);

        $this->revise($receipt, ['qty' => 8]);   // taking 2 out is fine
        $this->assertSame(0, $this->stock($p));
    }

    public function test_a_different_lot_moves_the_line_and_the_same_lot_can_have_its_expiry_corrected(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10, 'LOT-A', '2028-01-31');

        $this->revise($receipt, ['qty' => 10, 'batch_no' => 'LOT-A', 'expiration_date' => '2029-06-30']);
        $this->assertSame('2029-06-30', $p->batches()->where('batch_no', 'LOT-A')->first()->expiration_date->toDateString());
        $this->assertSame(10, $this->stock($p));

        $this->revise($receipt->fresh('items'), ['qty' => 10, 'batch_no' => 'LOT-B', 'expiration_date' => '2030-01-01']);
        $this->assertSame(0, $p->batches()->where('batch_no', 'LOT-A')->first()->qtyAtLocation($this->warehouse->id));
        $this->assertSame(10, $p->batches()->where('batch_no', 'LOT-B')->first()->qtyAtLocation($this->warehouse->id));
        $this->assertSame('LOT-B', $receipt->fresh('items')->items[0]->batch_no);
    }

    public function test_a_lot_whose_stock_is_gone_cannot_be_moved(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10, 'LOT-A');
        $this->deliveredOrder([[$p, 6, 100]]);

        $this->expectException(ValidationException::class);
        try {
            $this->revise($receipt, ['qty' => 10, 'batch_no' => 'LOT-X']);
        } finally {
            $this->assertSame(4, $this->stock($p));
            $this->assertSame('LOT-A', $receipt->fresh('items')->items[0]->batch_no);
        }
    }

    public function test_cost_remarks_date_and_supplier_can_be_corrected(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10, 'LOT-A', null, 5);
        $other = Supplier::factory()->create();

        $changes = $this->revise($receipt, ['qty' => 10, 'unit_cost' => 7.5, 'remarks' => 'checked'], ['supplier_id' => $other->id, 'receipt_date' => '2026-10-01']);

        $receipt = $receipt->fresh('items');
        $this->assertEquals(7.5, $receipt->items[0]->unit_cost);
        $this->assertEquals(7.5, $p->fresh()->unit_cost, 'the latest receipt sets the product cost');
        $this->assertSame('checked', $receipt->items[0]->remarks);
        $this->assertSame($other->id, $receipt->supplier_id);
        $this->assertSame('2026-10-01', $receipt->receipt_date->toDateString());
        $this->assertNotEmpty($changes);
    }

    public function test_an_older_receipt_does_not_overwrite_the_products_cost(): void
    {
        $p = $this->product('ITEM A');
        $old = $this->receipt($p, 5, 'LOT-A', null, 5);
        $this->receipt($p, 5, 'LOT-B', null, 9);   // the latest, product cost 9

        $this->revise($old, ['qty' => 5, 'batch_no' => 'LOT-A', 'unit_cost' => 6]);

        $this->assertEquals(9, $p->fresh()->unit_cost);
        $this->assertEquals(6, $old->fresh('items')->items[0]->unit_cost);
    }

    public function test_a_line_against_a_purchase_order_moves_what_the_order_has_received(): void
    {
        $category = Category::factory()->create();
        $generic = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'PO ITEM ' . uniqid()]);
        $p = Product::factory()->create(['generic_name_id' => $generic->id]);
        $supplier = Supplier::factory()->create();
        $po = app(PurchaseOrderService::class)->createPurchaseOrder([
            'supplier_id' => $supplier->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['generic_name_id' => $generic->id, 'qty' => 20, 'unit' => 'PC', 'unit_cost' => 10]],
        ])->fresh('items');
        $receipt = app(GoodsReceiptService::class)->createGoodsReceipt([
            'supplier_id' => $supplier->id, 'purchase_order_id' => $po->id, 'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_id' => $p->id, 'qty' => 12, 'unit_cost' => 10, 'purchase_order_item_id' => $po->items[0]->id]],
        ], $this->admin->id)->fresh('items');
        $this->assertSame('partially_received', $po->fresh()->status);

        $this->revise($receipt, ['qty' => 20]);
        $this->assertSame(20, $po->fresh('items')->items[0]->received_qty);
        $this->assertSame('completed', $po->fresh()->status);

        $this->revise($receipt->fresh('items'), ['qty' => 5]);
        $this->assertSame(5, $po->fresh('items')->items[0]->received_qty);
        $this->assertSame('partially_received', $po->fresh()->status);

        try {
            $this->revise($receipt->fresh('items'), ['qty' => 25]);   // the order only expects 20
            $this->fail('received more than the order expects');
        } catch (ValidationException) {
        }
        $this->assertSame(5, $po->fresh('items')->items[0]->received_qty);

        $this->revise($receipt->fresh('items'), ['qty' => 5, 'unit_cost' => 11], ['supplier_id' => Supplier::factory()->create()->id]);
        $this->assertSame($supplier->id, $receipt->fresh()->supplier_id, 'the supplier of a receipt against a PO stays the PO supplier');
    }

    public function test_nothing_changed_and_drafts_are_refused(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10);

        try {
            $this->revise($receipt, ['qty' => 10, 'unit_cost' => 5, 'batch_no' => 'LOT-A', 'expiration_date' => '2028-01-31']);
            $this->fail('an unchanged receipt was accepted');
        } catch (ValidationException) {
        }

        $receipt->update(['status' => 'draft']);
        $this->expectException(ValidationException::class);
        $this->revise($receipt->fresh('items'), ['qty' => 12]);
    }

    public function test_over_http_only_a_full_admin_edits_and_it_is_logged(): void
    {
        $p = $this->product('ITEM A');
        $receipt = $this->receipt($p, 10);
        $line = $receipt->items[0];
        $payload = ['reason' => 'Typed 10 instead of 12', 'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'lines' => [$line->id => ['qty' => 12, 'unit_cost' => 5, 'batch_no' => 'LOT-A', 'expiration_date' => '2028-01-31']]];

        $this->actingAs($this->admin)->get(route('goods-receipts.show', $receipt))->assertOk()->assertSee('Edit');
        $this->actingAs($this->admin)->get(route('goods-receipts.revise', $receipt))->assertOk()->assertSee('Save Changes');

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))->post(route('goods-receipts.apply-revision', $receipt), $payload);
        $this->assertSame(10, $this->stock($p));

        $this->actingAs($this->admin)->post(route('goods-receipts.apply-revision', $receipt), $payload)->assertRedirect(route('goods-receipts.show', $receipt));
        $this->assertSame(12, $this->stock($p));
        $this->assertDatabaseHas('activity_logs', ['module' => 'GoodsReceipt', 'action' => 'revised', 'loggable_id' => $receipt->id]);

        $this->actingAs($this->admin)->post(route('goods-receipts.apply-revision', $receipt), array_merge($payload, ['reason' => '']))->assertSessionHasErrors('reason');
    }
}
