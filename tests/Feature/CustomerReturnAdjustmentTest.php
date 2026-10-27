<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\InventoryAdjustment;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's workaround for goods a customer sends back before any Invoice exists: an Inventory Adjustment
 * (Stock In - Returned by Customer) that is linked to the Delivery Receipt they came from.
 */
class CustomerReturnAdjustmentTest extends TestCase
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
        $this->product = Product::factory()->create(['generic_name_id' => $generic->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->product->id, 'qty' => 20]],
        ], $this->admin->id);

        $this->dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'walk_in', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->product->batches()->first()->id, 'qty' => 10]],
        ], $this->admin->id);
    }

    protected function warehouseQty(): int
    {
        return (int) $this->product->fresh('batches')->batches->sum(fn ($b) => $b->qtyAtLocation(Location::warehouse()->id));
    }

    protected function payload(array $extra = []): array
    {
        return array_merge([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => InventoryAdjustment::CUSTOMER_RETURN,
            'description' => 'Returned by SAGADA LGU', 'delivery_receipt_id' => $this->dr->id,
            'lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => 2]],
        ], $extra);
    }

    public function test_a_customer_return_puts_the_stock_back_in_the_warehouse_and_points_at_the_dr(): void
    {
        $this->assertSame(10, $this->warehouseQty());

        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload())->assertRedirect();

        $adjustment = InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->firstOrFail();
        $this->assertSame($this->dr->id, $adjustment->delivery_receipt_id);
        $this->assertSame('in', $adjustment->direction());
        $this->assertSame(12, $this->warehouseQty());
    }

    public function test_a_customer_return_must_name_the_delivery_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload(['delivery_receipt_id' => '']))
            ->assertSessionHasErrors('delivery_receipt_id');

        $this->assertSame(10, $this->warehouseQty());
        $this->assertSame(0, InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->count());
    }

    public function test_other_types_ignore_a_delivery_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload(['adjustment_type' => 'stock_in_found']))->assertRedirect();

        $this->assertNull(InventoryAdjustment::where('adjustment_type', 'stock_in_found')->firstOrFail()->delivery_receipt_id);
    }

    public function test_a_draft_keeps_the_link_until_it_is_posted(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload(['save_action' => 'draft']));
        $draft = InventoryAdjustment::latest('id')->firstOrFail();
        $this->assertTrue($draft->isDraft());
        $this->assertSame($this->dr->id, $draft->delivery_receipt_id);
        $this->assertSame(10, $this->warehouseQty(), 'a draft moves no stock');

        $this->actingAs($this->admin)->put(route('inventory-adjustments.update', $draft), $this->payload());
        $this->assertSame(12, $this->warehouseQty());
        $this->assertSame($this->dr->id, $draft->fresh()->delivery_receipt_id);
    }

    public function test_the_adjustment_the_dr_and_product_history_link_to_each_other(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload());
        $adjustment = InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->firstOrFail();

        $this->actingAs($this->admin)->get(route('inventory-adjustments.show', $adjustment))
            ->assertOk()->assertSee($this->dr->dr_no)->assertSee('SAGADA LOCAL GOVERNMENT UNIT');

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))
            ->assertOk()->assertSee('Returned by the customer')->assertSee($adjustment->adjustment_no);

        $this->actingAs($this->admin)->get(route('inventory-items.index', ['tab' => 'history', 'product_id' => $this->product->id]))
            ->assertOk()->assertSee(route('delivery-receipts.show', $this->dr), false)->assertSee('Returned by Customer')->assertSee($this->dr->dr_no);
    }

    public function test_writing_off_a_return_takes_the_stock_out_again(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload());
        $adjustment = InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->firstOrFail();

        $this->actingAs($this->admin)->post(route('inventory-adjustments.write-off', $adjustment));

        $this->assertSame(10, $this->warehouseQty());
    }

    // ---- a return cannot be more than the Delivery Receipt delivered

    public function test_it_cannot_return_more_than_the_dr_delivered(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload([
            'lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => 11]],
        ]))->assertSessionHasErrors('lines');

        $this->assertSame(0, InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->count());
        $this->assertSame(10, $this->warehouseQty(), 'no stock moved');
    }

    public function test_what_is_left_after_earlier_returns_is_the_limit(): void
    {
        $line = fn (int $qty) => ['lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => $qty]]];

        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload($line(7)))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload($line(4)))->assertSessionHasErrors('lines');   // 3 left
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload($line(3)))->assertSessionHasNoErrors();

        $this->assertSame(2, InventoryAdjustment::where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->count());
        $this->assertSame(20, $this->warehouseQty());
    }

    public function test_a_return_item_against_the_same_dr_counts_too(): void
    {
        \App\Models\ReturnItem::create([
            'product_batch_id' => $this->product->batches()->first()->id, 'user_id' => $this->admin->id, 'customer_id' => $this->customer->id,
            'delivery_receipt_id' => $this->dr->id, 'quantity' => 9, 'return_date' => now()->toDateString(), 'reason' => 'Changed Mind',
            'status' => 'pending', 'is_draft' => false,
        ]);

        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload())->assertSessionHasErrors('lines');   // 2 asked, 1 left
    }

    public function test_an_item_the_dr_never_delivered_is_refused(): void
    {
        $generic = \App\Models\GenericName::factory()->create(['category_id' => \App\Models\Category::factory()->create()->id]);
        $other = Product::factory()->create(['generic_name_id' => $generic->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $other->id, 'qty' => 5]],
        ], $this->admin->id);

        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload([
            'lines' => [['product_id' => $other->id, 'product_batch_id' => $other->batches()->first()->id, 'qty' => 1]],
        ]))->assertSessionHasErrors('delivery_receipt_id');
    }

    public function test_a_draft_may_be_saved_over_the_limit_but_not_posted(): void
    {
        $over = ['lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => 50]]];

        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload($over + ['save_action' => 'draft']))->assertSessionHasNoErrors();
        $draft = InventoryAdjustment::latest('id')->firstOrFail();
        $this->assertTrue($draft->isDraft());

        $this->actingAs($this->admin)->put(route('inventory-adjustments.update', $draft), $this->payload($over))->assertSessionHasErrors('lines');
        $this->assertTrue($draft->fresh()->isDraft());
        $this->assertSame(10, $this->warehouseQty());
    }

    public function test_other_adjustment_types_are_not_limited_by_a_dr(): void
    {
        $this->actingAs($this->admin)->post(route('inventory-adjustments.store'), $this->payload([
            'adjustment_type' => 'stock_in_found',
            'lines' => [['product_id' => $this->product->id, 'product_batch_id' => $this->product->batches()->first()->id, 'qty' => 99]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(109, $this->warehouseQty());
    }
}
