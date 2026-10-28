<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's case: a Delivery Receipt was posted before the goods were picked, then an item turned out
 * to be missing and another short. Revising it returns the stock and reopens the Sales Order line.
 */
class ReviseDeliveryReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Location $warehouse;
    protected DeliveryReceiptService $drs;

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
        $this->drs = app(DeliveryReceiptService::class);
    }

    /** @return array{0: DeliveryReceipt, 1: SalesOrder, 2: array<int,Product>} A posted DR: A 10 of 10 and B 5 of 5, each product with 20 / 10 in stock first. */
    protected function postedReceipt(): array
    {
        $category = Category::factory()->create();
        $products = [];
        $lines = [];
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'so_no' => 'SO-RV-' . SalesOrder::count(), 'status' => 'open', 'is_draft' => false,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);

        foreach ([['A', 20, 10], ['B', 10, 5]] as [$name, $stock, $ordered]) {
            $generic = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'ITEM ' . $name . ' ' . uniqid()]);
            $product = Product::factory()->create(['generic_name_id' => $generic->id, 'brand_name' => $name, 'description' => "ITEM {$name}, 5CM"]);
            app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'qty' => $stock]],
            ], $this->admin->id);

            $soItem = $so->items()->create(['generic_name_id' => $generic->id, 'qty' => $ordered, 'price' => 100, 'delivered_qty' => 0]);
            $lines[] = ['product_batch_id' => $product->batches()->first()->id, 'qty' => $ordered, 'sales_order_item_id' => $soItem->id];
            $products[] = $product;
        }

        $dr = $this->drs->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'items' => $lines,
        ], $this->admin->id);

        return [$dr->fresh('items'), $so->fresh('items'), $products];
    }

    protected function stock(Product $product): int
    {
        return (int) $product->fresh('batches')->batches->sum(fn ($batch) => $batch->qtyAtLocation($this->warehouse->id));
    }

    public function test_revising_returns_the_stock_and_reopens_the_sales_order_line(): void
    {
        [$dr, $so, [$a, $b]] = $this->postedReceipt();
        $this->assertSame('completed', $so->status);
        $this->assertSame([10, 5], [$this->stock($a), $this->stock($b)]);

        $changes = $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 7, $dr->items[1]->id => 0], $this->admin->id);

        $this->assertCount(2, $changes);
        $this->assertSame([13, 10], [$this->stock($a), $this->stock($b)]);

        $dr = $dr->fresh('items');
        $this->assertSame([7], $dr->items->pluck('qty')->all(), 'line B was taken off, line A lowered');
        $so = $so->fresh('items');
        $this->assertSame([7, 0], $so->items->pluck('delivered_qty')->all());
        $this->assertSame('partially_delivered', $so->status);
    }

    public function test_a_blank_quantity_leaves_that_line_unchanged(): void
    {
        [$dr, , [$a, $b]] = $this->postedReceipt();

        $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 8, $dr->items[1]->id => ''], $this->admin->id);

        $this->assertSame([8, 5], $dr->fresh('items')->items->pluck('qty')->all());
        $this->assertSame([12, 5], [$this->stock($a), $this->stock($b)]);
    }

    public function test_guards_reject_bad_input_and_change_nothing(): void
    {
        [$dr, $so, [$a, $b]] = $this->postedReceipt();
        [$la, $lb] = [$dr->items[0], $dr->items[1]];

        $cases = [
            'above what was delivered' => [$la->id => 11],
            'negative' => [$la->id => -1],
            'nothing changed' => [$la->id => 10, $lb->id => 5],
            'every line taken off' => [$la->id => 0, $lb->id => 0],
        ];
        foreach ($cases as $label => $qtys) {
            try {
                $this->drs->reviseDeliveryReceipt($dr, $qtys, $this->admin->id);
                $this->fail("{$label} was accepted");
            } catch (ValidationException) {
            }
        }

        $this->assertSame([10, 5], $dr->fresh('items')->items->pluck('qty')->all());
        $this->assertSame([10, 5], [$this->stock($a), $this->stock($b)]);
        $this->assertSame('completed', $so->fresh()->status);
    }

    public function test_a_line_cannot_go_below_what_is_already_invoiced(): void
    {
        [$dr, , [$a]] = $this->postedReceipt();
        $dr->items[0]->update(['invoiced_qty' => 6]);

        try {
            $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 5], $this->admin->id);
            $this->fail('went below the invoiced quantity');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already invoiced', $e->errors()['revise'][0]);
        }

        $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 6], $this->admin->id);   // exactly the invoiced qty is fine
        $this->assertSame(6, $dr->items[0]->fresh()->qty);
        $this->assertSame(14, $this->stock($a));
    }

    public function test_only_a_posted_receipt_that_is_still_for_delivery_can_be_revised(): void
    {
        [$delivered] = $this->postedReceipt();
        $delivered->update(['status' => 'delivered']);

        [$cancelled] = $this->postedReceipt();
        $cancelled->update(['status' => 'cancelled']);

        [$draft] = $this->postedReceipt();
        $draft->update(['is_draft' => true]);

        foreach ([$delivered, $cancelled, $draft] as $receipt) {
            try {
                $this->drs->reviseDeliveryReceipt($receipt, [$receipt->items[0]->id => 1], $this->admin->id);
                $this->fail('a receipt that is not For Delivery was revised');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(10, $delivered->items[0]->fresh()->qty);
    }

    public function test_only_a_full_admin_can_revise_over_http(): void
    {
        [$dr] = $this->postedReceipt();
        $payload = ['reason' => 'Item missing', 'new_qty' => [$dr->items[0]->id => 4]];

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('delivery-receipts.revise', $dr), $payload);
        $this->assertSame(10, $dr->items[0]->fresh()->qty);
    }

    public function test_full_admin_revises_over_http_and_it_is_recorded(): void
    {
        [$dr] = $this->postedReceipt();

        $this->actingAs($this->admin)
            ->post(route('delivery-receipts.revise', $dr), ['reason' => 'Item missing', 'new_qty' => [$dr->items[0]->id => 4]])
            ->assertRedirect(route('delivery-receipts.show', $dr));

        $this->assertSame(4, $dr->items[0]->fresh()->qty);
        $this->assertDatabaseHas('activity_logs', ['module' => 'DeliveryReceipt', 'action' => 'revised', 'loggable_id' => $dr->id]);
    }

    public function test_a_line_can_be_raised_within_what_the_sales_order_still_has_to_deliver(): void
    {
        [$dr, $so, [$a, $b]] = $this->postedReceipt();
        $so->items[0]->update(['qty' => 15]);   // the order owes 5 more of A

        $changes = $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 13], $this->admin->id);

        $this->assertSame([['item' => $a->display_description, 'batch' => $changes[0]['batch'], 'from' => 10, 'to' => 13]], $changes);
        $this->assertSame(13, $dr->fresh('items')->items[0]->qty);
        $this->assertSame(7, $this->stock($a), 'the 3 extra came out of the Warehouse');
        $this->assertSame(13, $so->fresh('items')->items[0]->delivered_qty);
        $this->assertSame('partially_delivered', $so->fresh()->status);

        try {
            $this->drs->reviseDeliveryReceipt($dr->fresh('items'), [$dr->items[0]->id => 16], $this->admin->id);
            $this->fail('a raise past the Sales Order balance was accepted');
        } catch (ValidationException) {
        }
        $this->assertSame(13, $dr->fresh('items')->items[0]->qty);
        $this->assertSame(7, $this->stock($a));
    }

    public function test_a_raise_cannot_take_more_than_the_warehouse_has(): void
    {
        [$dr, $so, [$a]] = $this->postedReceipt();
        $so->items[0]->update(['qty' => 100]);

        $this->expectException(ValidationException::class);
        try {
            $this->drs->reviseDeliveryReceipt($dr, [$dr->items[0]->id => 25], $this->admin->id);   // only 10 left
        } finally {
            $this->assertSame(10, $dr->fresh('items')->items[0]->qty);
            $this->assertSame(10, $this->stock($a));
        }
    }

    public function test_an_item_can_be_added_to_the_receipt_and_it_counts_on_the_sales_order(): void
    {
        [$dr, $so, [$a, $b]] = $this->postedReceipt();
        $so->items[1]->update(['qty' => 9]);   // B was 5, all delivered; the order now owes 4 more

        // a second batch of B (a different lot) is what gets added
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $b->id, 'qty' => 6, 'batch_no' => 'LOT-B2']],
        ], $this->admin->id);
        $newBatch = $b->batches()->where('batch_no', 'LOT-B2')->first();

        $changes = $this->drs->reviseDeliveryReceipt($dr, [], $this->admin->id, [['product_batch_id' => $newBatch->id, 'qty' => 4], ['product_batch_id' => '', 'qty' => '']]);

        $this->assertSame([0, 4], [$changes[0]['from'], $changes[0]['to']]);
        $dr = $dr->fresh('items');
        $this->assertCount(3, $dr->items);
        $this->assertSame([$so->items[1]->id, 4], [$dr->items[2]->sales_order_item_id, $dr->items[2]->qty]);
        $this->assertSame(2, $newBatch->fresh()->qtyAtLocation($this->warehouse->id));
        $this->assertSame(9, $so->fresh('items')->items[1]->delivered_qty);
        $this->assertSame('completed', $so->fresh()->status);
    }

    public function test_an_added_item_must_be_on_the_sales_order_with_something_left_to_deliver(): void
    {
        [$dr, $so, [$a]] = $this->postedReceipt();
        $other = Product::factory()->create(['generic_name_id' => GenericName::factory()->create(['category_id' => Category::factory()->create()->id])->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $other->id, 'qty' => 5]],
        ], $this->admin->id);

        foreach ([[$other->batches()->first()->id, 'not on the order'], [$a->batches()->first()->id, 'order already fully delivered']] as [$batchId, $label]) {
            try {
                $this->drs->reviseDeliveryReceipt($dr, [], $this->admin->id, [['product_batch_id' => $batchId, 'qty' => 1]]);
                $this->fail("{$label} was accepted");
            } catch (ValidationException) {
            }
        }

        $this->assertCount(2, $dr->fresh('items')->items);
        $this->assertSame(5, $this->stock($other));
    }

    public function test_an_item_can_be_added_to_a_receipt_with_no_sales_order(): void
    {
        [$dr, , [$a, $b]] = $this->postedReceipt();
        $walkIn = $this->drs->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'advance_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id, 'items' => [['product_batch_id' => $a->batches()->first()->id, 'qty' => 2]],
        ], $this->admin->id);

        $this->drs->reviseDeliveryReceipt($walkIn, [], $this->admin->id, [['product_batch_id' => $b->batches()->first()->id, 'qty' => 3]]);

        $this->assertCount(2, $walkIn->fresh('items')->items);
        $this->assertSame(2, $this->stock($b));
    }

    public function test_adding_and_raising_work_over_http(): void
    {
        [$dr, $so, [$a]] = $this->postedReceipt();
        $so->items[0]->update(['qty' => 14]);

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $dr))->assertOk()->assertSee('Add an item to this receipt');

        $this->actingAs($this->admin)->post(route('delivery-receipts.revise', $dr), [
            'reason' => 'Customer wants 2 more', 'new_qty' => [$dr->items[0]->id => 12], 'add' => [['product_batch_id' => '', 'qty' => '']],
        ])->assertRedirect(route('delivery-receipts.show', $dr));

        $this->assertSame(12, $dr->fresh('items')->items[0]->qty);
        $this->assertSame(8, $this->stock($a));
        $this->assertDatabaseHas('activity_logs', ['module' => 'DeliveryReceipt', 'action' => 'revised', 'loggable_id' => $dr->id]);
    }

    public function test_the_add_item_search_lists_warehouse_lots_for_a_full_admin_only(): void
    {
        [$dr, , [$a, $b]] = $this->postedReceipt();
        $route = route('delivery-receipts.addable-batches', $dr);

        $labels = collect($this->actingAs($this->admin)->getJson($route)->assertOk()->json())->pluck('label')->all();
        $this->assertCount(2, $labels);
        $this->assertStringContainsString('ITEM A', $labels[0] . $labels[1]);
        $this->assertStringContainsString('in Warehouse', $labels[0]);

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))->getJson($route)->assertForbidden();

        $dr->update(['status' => 'delivered']);
        $this->actingAs($this->admin)->getJson($route)->assertForbidden();
    }

    public function test_a_reason_is_required(): void
    {
        [$dr] = $this->postedReceipt();

        $this->actingAs($this->admin)
            ->post(route('delivery-receipts.revise', $dr), ['reason' => '', 'new_qty' => [$dr->items[0]->id => 4]])
            ->assertSessionHasErrors('reason');

        $this->assertSame(10, $dr->items[0]->fresh()->qty);
    }
}
