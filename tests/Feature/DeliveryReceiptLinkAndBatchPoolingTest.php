<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\GoodsReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's DR-from-SO concern: no-Lot receipts pool into one batch, a Sales Order line can be
 * delivered from several batches, and an Advance Order DR can be linked (whole or in part) to
 * a Sales Order afterwards.
 */
class DeliveryReceiptLinkAndBatchPoolingTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected User $admin;
    protected Location $warehouse;
    protected DeliveryReceiptService $drs;
    protected InventoryAdjustmentService $adjustments;

    /**
     * RefreshDatabase wipes whatever database is configured. The project's migrations use a
     * fulltext index (MySQL only), so run this against a scratch MySQL database, e.g.
     * DB_CONNECTION=mysql DB_DATABASE=inventory_app_test — and refuse to touch anything else.
     */
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

        $this->customer = Customer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->warehouse = Location::warehouse();
        $this->drs = app(DeliveryReceiptService::class);
        $this->adjustments = app(InventoryAdjustmentService::class);
    }

    protected function makeProduct(string $name): Product
    {
        $category = Category::factory()->create();
        $generic = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => $name]);

        return Product::factory()->create(['generic_name_id' => $generic->id, 'brand_name' => $name, 'item_name' => $name]);
    }

    protected function openingBalance(Product $product, int $qty, array $extra = []): void
    {
        $this->adjustments->createAdjustment([
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'opening_balance',
            'prepared_by' => $this->admin->id,
            'lines' => [array_merge(['product_id' => $product->id, 'qty' => $qty], $extra)],
        ], $this->admin->id);
    }

    protected function stock(Product $product): int
    {
        return (int) $product->batches->sum(fn ($batch) => $batch->qtyAtLocation($this->warehouse->id));
    }

    protected function salesOrder(array $lines): SalesOrder
    {
        $so = SalesOrder::create([
            'customer_id' => $this->customer->id, 'so_no' => 'SO-T-' . SalesOrder::count(), 'status' => 'open',
            'is_draft' => false, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);
        foreach ($lines as [$product, $qty]) {
            $so->items()->create(['generic_name_id' => $product->generic_name_id, 'qty' => $qty, 'price' => 100, 'delivered_qty' => 0]);
        }

        return $so->fresh('items');
    }

    protected function advanceDr(array $lines): DeliveryReceipt
    {
        return $this->drs->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'advance_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => collect($lines)->map(fn ($l) => ['product_batch_id' => $l[0]->batches()->first()->id, 'qty' => $l[1]])->all(),
        ], $this->admin->id);
    }

    // ---------------------------------------------------------------- no-Lot pooling

    public function test_no_lot_adjustments_pool_into_one_batch(): void
    {
        $product = $this->makeProduct('ITEM A');
        $this->openingBalance($product, 15);
        $this->openingBalance($product, 4);

        $this->assertSame(1, $product->batches()->count());
        $this->assertSame(19, $this->stock($product->fresh('batches')));
    }

    public function test_pooling_keys_on_lot_and_expiry(): void
    {
        $product = $this->makeProduct('ITEM B');
        $this->openingBalance($product, 1);
        $this->openingBalance($product, 2);                                   // same no-lot/no-expiry -> pooled
        $this->openingBalance($product, 3, ['expiration_date' => '2030-01-01']);
        $this->openingBalance($product, 4, ['expiration_date' => '2030-01-01']); // same expiry -> pooled
        $this->openingBalance($product, 5, ['batch_no' => 'LOT-X']);
        $this->openingBalance($product, 6, ['batch_no' => 'LOT-X']);          // same typed lot -> pooled

        $this->assertSame(3, $product->batches()->count());
        $this->assertEqualsCanonicalizing([3, 7, 11], $product->batches->map(fn ($b) => $b->qtyAtLocation($this->warehouse->id))->all());
    }

    public function test_goods_receipts_pool_no_lot_lines_too(): void
    {
        $product = $this->makeProduct('ITEM C');
        $supplier = Supplier::factory()->create();
        $service = app(GoodsReceiptService::class);

        foreach ([6, 9] as $qty) {
            $service->createGoodsReceipt([
                'supplier_id' => $supplier->id, 'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
                'items' => [['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => 10]],
            ], $this->admin->id);
        }

        $this->assertSame(1, $product->batches()->count());
        $this->assertSame(15, $this->stock($product->fresh('batches')));
    }

    // ---------------------------------------------------------------- one SO line, several batches

    public function test_one_sales_order_line_can_be_delivered_from_two_batches_and_rejects_overage(): void
    {
        $product = $this->makeProduct('ITEM D');
        $this->openingBalance($product, 15, ['batch_no' => 'A']);
        $this->openingBalance($product, 4, ['batch_no' => 'B']);
        $so = $this->salesOrder([[$product, 19]]);
        $item = $so->items->first();
        [$a, $b] = [$product->batches()->where('batch_no', 'A')->first(), $product->batches()->where('batch_no', 'B')->first()];

        $deliver = fn (array $qtys) => $this->drs->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [
                ['product_batch_id' => $a->id, 'qty' => $qtys[0], 'sales_order_item_id' => $item->id],
                ['product_batch_id' => $b->id, 'qty' => $qtys[1], 'sales_order_item_id' => $item->id],
            ],
        ], $this->admin->id);

        try {
            $deliver([15, 5]); // 20 > 19, and 5 > the 4 in stock
            $this->fail('over-delivery was accepted');
        } catch (ValidationException) {
        }
        $this->assertSame(0, $item->fresh()->delivered_qty);
        $this->assertSame(19, $this->stock($product->fresh('batches')));

        $deliver([15, 4]);
        $this->assertSame(19, $item->fresh()->delivered_qty);
        $this->assertSame('completed', $so->fresh()->status);
        $this->assertSame(0, $this->stock($product->fresh('batches')));
    }

    // ---------------------------------------------------------------- linking an Advance Order DR

    public function test_linking_everything_turns_the_same_dr_into_the_purchase_order_dr(): void
    {
        $product = $this->makeProduct('ITEM E');
        $this->openingBalance($product, 19);
        $so = $this->salesOrder([[$product, 19]]);
        $dr = $this->advanceDr([[$product, 19]]);
        $line = $dr->items->first();

        $linked = $this->drs->linkToSalesOrder($dr, $so, [$line->id => $so->items->first()->id], $this->admin->id);

        $this->assertSame($dr->id, $linked->id);
        $this->assertSame(1, DeliveryReceipt::count());
        $this->assertSame('purchase_order', $dr->fresh()->transaction_type);
        $this->assertSame($so->id, $dr->fresh()->sales_order_id);
        $this->assertSame('completed', $so->fresh()->status);
        $this->assertSame(0, $this->stock($product->fresh('batches')));
    }

    public function test_partial_link_moves_the_linked_part_to_a_new_dr_and_leaves_the_rest_as_advance_order(): void
    {
        [$a, $b, $c] = [$this->makeProduct('A'), $this->makeProduct('B'), $this->makeProduct('C')];
        foreach ([[$a, 20], [$b, 10], [$c, 10]] as [$p, $q]) {
            $this->openingBalance($p, $q);
        }
        $so = $this->salesOrder([[$a, 5], [$b, 10]]);
        $dr = $this->advanceDr([[$a, 20], [$b, 10], [$c, 10]]);
        [$la, $lb] = [$dr->items[0], $dr->items[1]];
        [$soA, $soB] = [$so->items[0], $so->items[1]];

        $linked = $this->drs->linkToSalesOrder($dr, $so, [$la->id => $soA->id, $lb->id => $soB->id], $this->admin->id, [$la->id => 5]);

        $this->assertNotSame($dr->id, $linked->id);
        $this->assertSame(2, DeliveryReceipt::count());
        $this->assertSame('purchase_order', $linked->transaction_type);
        $this->assertSame($so->id, $linked->sales_order_id);
        $this->assertSame($dr->id, $linked->split_from_delivery_receipt_id);
        $this->assertSame($dr->created_at->toDateString(), $linked->created_at->toDateString());
        $this->assertSame($dr->receipt_date->toDateString(), $linked->receipt_date->toDateString());

        $this->assertEqualsCanonicalizing([5, 10], $linked->items->pluck('qty')->all());
        $this->assertTrue($linked->items->every(fn ($i) => $i->sales_order_item_id !== null));

        $original = $dr->fresh('items');
        $this->assertSame('advance_order', $original->transaction_type);
        $this->assertNull($original->sales_order_id);
        $this->assertEqualsCanonicalizing([15, 10], $original->items->pluck('qty')->all());
        $this->assertTrue($original->items->every(fn ($i) => $i->sales_order_item_id === null));

        $this->assertSame('completed', $so->fresh()->status);
        $this->assertEqualsCanonicalizing([5, 10], $so->fresh()->items->pluck('delivered_qty')->all());

        // nothing moved: the stock was already deducted when the Advance DR was posted
        foreach ([$a, $b, $c] as $p) {
            $this->assertSame(0, $this->stock($p->fresh('batches')));
        }
    }

    public function test_the_leftover_can_be_linked_again_to_another_sales_order(): void
    {
        [$a, $c] = [$this->makeProduct('A2'), $this->makeProduct('C2')];
        $this->openingBalance($a, 20);
        $this->openingBalance($c, 10);
        $first = $this->salesOrder([[$a, 5]]);
        $second = $this->salesOrder([[$a, 15], [$c, 10]]);
        $dr = $this->advanceDr([[$a, 20], [$c, 10]]);

        $this->drs->linkToSalesOrder($dr, $first, [$dr->items[0]->id => $first->items[0]->id], $this->admin->id, [$dr->items[0]->id => 5]);

        $leftover = $dr->fresh('items');
        $linked = $this->drs->linkToSalesOrder(
            $leftover, $second,
            [$leftover->items[0]->id => $second->items[0]->id, $leftover->items[1]->id => $second->items[1]->id],
            $this->admin->id
        );

        $this->assertSame($dr->id, $linked->id, 'everything left was linked, so the DR itself becomes the PO DR');
        $this->assertSame(2, DeliveryReceipt::count());
        $this->assertSame('purchase_order', $dr->fresh()->transaction_type);
        $this->assertSame($second->id, $dr->fresh()->sales_order_id);
        $this->assertSame('completed', $second->fresh()->status);
    }

    public function test_link_guards_reject_bad_input_and_change_nothing(): void
    {
        [$a, $b] = [$this->makeProduct('G-A'), $this->makeProduct('G-B')];
        $this->openingBalance($a, 20);
        $this->openingBalance($b, 10);
        $so = $this->salesOrder([[$a, 5]]);
        $dr = $this->advanceDr([[$a, 20], [$b, 10]]);
        [$la, $lb] = [$dr->items[0], $dr->items[1]];
        $soA = $so->items->first();

        $cases = [
            'qty above the line' => fn () => $this->drs->linkToSalesOrder($dr, $so, [$la->id => $soA->id], null, [$la->id => 25]),
            'different generic' => fn () => $this->drs->linkToSalesOrder($dr, $so, [$lb->id => $soA->id]),
            'above the SO remaining' => fn () => $this->drs->linkToSalesOrder($dr, $so, [$la->id => $soA->id]),
            'nothing selected' => fn () => $this->drs->linkToSalesOrder($dr, $so, []),
        ];
        foreach ($cases as $label => $attempt) {
            try {
                $attempt();
                $this->fail("{$label} was accepted");
            } catch (ValidationException) {
            }
        }

        // a line that is already mostly invoiced can't be split below what's invoiced
        $la->update(['invoiced_qty' => 18]);
        try {
            $this->drs->linkToSalesOrder($dr->fresh(), $so, [$la->id => $soA->id], null, [$la->id => 5]);
            $this->fail('split of an invoiced line was accepted');
        } catch (ValidationException) {
        }

        $this->assertSame(1, DeliveryReceipt::count());
        $this->assertSame('advance_order', $dr->fresh()->transaction_type);
        $this->assertSame(0, $soA->fresh()->delivered_qty);
        $this->assertSame(2, $dr->items()->count());
    }

    public function test_a_customer_mismatch_or_an_already_linked_dr_cannot_be_linked(): void
    {
        $product = $this->makeProduct('M-A');
        $this->openingBalance($product, 10);
        $dr = $this->advanceDr([[$product, 10]]);
        $line = $dr->items->first();

        $other = SalesOrder::create([
            'customer_id' => Customer::factory()->create()->id, 'so_no' => 'SO-OTHER', 'status' => 'open', 'is_draft' => false,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);
        $other->items()->create(['generic_name_id' => $product->generic_name_id, 'qty' => 10, 'price' => 100, 'delivered_qty' => 0]);

        $this->expectException(ValidationException::class);
        $this->drs->linkToSalesOrder($dr, $other->fresh(), [$line->id => $other->items->first()->id]);
    }

    // ---------------------------------------------------------------- who may do it

    protected function linkScenario(): array
    {
        $product = $this->makeProduct('H-A');
        $this->openingBalance($product, 10);
        $so = $this->salesOrder([[$product, 10]]);
        $dr = $this->advanceDr([[$product, 10]]);
        $payload = ['sales_order_id' => $so->id, 'line_map' => [$dr->items->first()->id => $so->items->first()->id]];

        return [$so, $dr, $payload];
    }

    public function test_admin_staff_cannot_link_over_http(): void
    {
        [$so, $dr, $payload] = $this->linkScenario();

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('delivery-receipts.link-sales-order', $dr), $payload)
            ->assertRedirect(route('delivery-receipts.show', $dr));

        $this->assertSame('advance_order', $dr->fresh()->transaction_type);
        $this->assertNull($dr->fresh()->sales_order_id);
        $this->assertSame(0, $so->items->first()->fresh()->delivered_qty);
    }

    public function test_full_admin_can_link_over_http(): void
    {
        [$so, $dr, $payload] = $this->linkScenario();

        $this->actingAs($this->admin)
            ->post(route('delivery-receipts.link-sales-order', $dr), $payload)
            ->assertRedirect(route('delivery-receipts.show', $dr));

        $this->assertSame('purchase_order', $dr->fresh()->transaction_type);
        $this->assertSame('completed', $so->fresh()->status);
    }
}
