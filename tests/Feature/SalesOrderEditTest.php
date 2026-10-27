<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's rules for correcting an issued Sales Order: a line with deliveries can only be lowered (never below what was
 * delivered plus its advance quantity) and cannot be removed or swapped; a line with nothing delivered is free.
 */
class SalesOrderEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected GenericName $a;
    protected GenericName $b;
    protected GenericName $c;
    protected Product $productA;
    protected SalesOrder $so;
    protected SalesOrderService $service;

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
        $this->service = app(SalesOrderService::class);

        $category = Category::factory()->create();
        $this->a = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'ITEM A']);
        $this->b = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'ITEM B']);
        $this->c = GenericName::factory()->create(['category_id' => $category->id, 'generic_name' => 'ITEM C']);

        $this->productA = Product::factory()->create(['generic_name_id' => $this->a->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->productA->id, 'qty' => 50]],
        ], $this->admin->id);

        // A: 10 ordered, 6 delivered. B: 4 ordered, nothing delivered.
        $this->so = $this->service->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'po_no' => 'PO-1',
            'items' => [
                ['generic_name_id' => $this->a->id, 'qty' => 10, 'price' => 100],
                ['generic_name_id' => $this->b->id, 'qty' => 4, 'price' => 50],
            ],
        ]);
        $lineA = $this->so->items()->orderBy('id')->first();
        app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->productA->batches()->first()->id, 'qty' => 6, 'sales_order_item_id' => $lineA->id]],
        ], $this->admin->id);
        $this->so = $this->so->fresh();
    }

    protected function lines(): array
    {
        return $this->so->items()->orderBy('id')->get()->all();
    }

    /** The form's payload for the two existing lines, with changes applied on top. */
    protected function payload(array $lineA = [], array $lineB = [], array $extra = [], array $header = []): array
    {
        [$a, $b] = $this->lines();

        return array_merge([
            'po_no' => 'PO-1', 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => array_merge([
                array_merge(['id' => $a->id, 'generic_name_id' => $a->generic_name_id, 'qty' => $a->qty, 'price' => 100, 'advance_order_qty' => 0], $lineA),
                array_merge(['id' => $b->id, 'generic_name_id' => $b->generic_name_id, 'qty' => $b->qty, 'price' => 50, 'advance_order_qty' => 0], $lineB),
            ], $extra),
        ], $header);
    }

    public function test_a_delivered_line_can_be_lowered_down_to_what_was_delivered(): void
    {
        $this->service->updateIssuedOrder($this->so, $this->payload(['qty' => 8]));
        $this->assertSame(8, $this->lines()[0]->qty);

        $changes = $this->service->updateIssuedOrder($this->so, $this->payload(['qty' => 6]));
        $this->assertSame(6, $this->lines()[0]->qty);
        $this->assertSame(['ITEM A qty 8 -> 6'], $changes);
        $this->assertSame(6, $this->lines()[0]->delivered_qty, 'the delivery is kept');
    }

    public function test_a_delivered_line_cannot_go_below_what_was_delivered(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->updateIssuedOrder($this->so, $this->payload(['qty' => 5]));
        } finally {
            $this->assertSame(10, $this->lines()[0]->qty);
        }
    }

    public function test_a_delivered_line_cannot_be_removed(): void
    {
        [, $b] = $this->lines();
        $payload = $this->payload();
        array_shift($payload['items']);   // drop line A

        $this->expectException(ValidationException::class);
        $this->service->updateIssuedOrder($this->so, $payload);
        $this->assertSame(2, $this->so->items()->count());
    }

    public function test_a_delivered_line_keeps_its_item_and_price_even_if_the_form_sends_others(): void
    {
        $this->service->updateIssuedOrder($this->so, $this->payload(['generic_name_id' => $this->c->id, 'price' => 999, 'qty' => 9, 'remarks' => 'fixed']));

        $line = $this->lines()[0];
        $this->assertSame($this->a->id, $line->generic_name_id);
        $this->assertEquals(100, $line->price);
        $this->assertSame(9, $line->qty);
        $this->assertSame('fixed', $line->remarks);
    }

    public function test_a_line_with_nothing_delivered_is_free(): void
    {
        [, $b] = $this->lines();

        $this->service->updateIssuedOrder($this->so, $this->payload([], ['generic_name_id' => $this->c->id, 'qty' => 7, 'price' => 80, 'remarks' => 'swapped']));

        $line = $b->fresh();
        $this->assertSame([$this->c->id, 7], [$line->generic_name_id, $line->qty]);
        $this->assertEquals(80, $line->price);
        $this->assertSame($b->id, $line->id, 'same line, so nothing pointing at it breaks');
    }

    public function test_a_line_with_nothing_delivered_can_be_removed_and_new_lines_added(): void
    {
        $payload = $this->payload();
        array_pop($payload['items']);   // drop B
        $payload['items'][] = ['generic_name_id' => $this->c->id, 'qty' => 3, 'price' => 20, 'advance_order_qty' => 0];

        $changes = $this->service->updateIssuedOrder($this->so, $payload);

        $this->assertSame(['ITEM A', 'ITEM C'], $this->so->items()->orderBy('id')->get()->map(fn ($i) => $i->genericName->generic_name)->all());
        $this->assertContains('removed ITEM B', $changes);
        $this->assertContains('added ITEM C (3)', $changes);
    }

    public function test_a_line_that_a_draft_delivery_receipt_points_at_cannot_be_removed_either(): void
    {
        [, $b] = $this->lines();
        $draft = app(DeliveryReceiptService::class)->saveDraft([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'items' => [['product_batch_id' => $this->productA->batches()->first()->id, 'qty' => 1, 'sales_order_item_id' => $b->id]],
        ], $this->admin->id);
        $this->assertTrue($draft->is_draft);

        $payload = $this->payload();
        array_pop($payload['items']);

        $this->expectException(ValidationException::class);
        $this->service->updateIssuedOrder($this->so, $payload);
    }

    public function test_the_advance_quantity_is_part_of_the_floor(): void
    {
        [, $b] = $this->lines();
        $b->update(['advance_order_qty' => 3]);

        $this->expectException(ValidationException::class);
        $this->service->updateIssuedOrder($this->so, $this->payload([], ['qty' => 2, 'advance_order_qty' => 3]));
    }

    public function test_the_order_status_follows_the_quantities(): void
    {
        // A lowered to 6 (all delivered) and B removed: everything delivered -> completed
        $payload = $this->payload(['qty' => 6]);
        array_pop($payload['items']);
        $this->service->updateIssuedOrder($this->so, $payload);
        $this->assertSame('completed', $this->so->fresh()->status);

        // raise A again: something is owed -> partially delivered
        $this->service->updateIssuedOrder($this->so->fresh(), [
            'order_date' => now()->toDateString(),
            'items' => [['id' => $this->lines()[0]->id, 'generic_name_id' => $this->a->id, 'qty' => 9, 'price' => 100]],
        ]);
        $this->assertSame('partially_delivered', $this->so->fresh()->status);
    }

    public function test_the_header_can_be_corrected(): void
    {
        $this->service->updateIssuedOrder($this->so, $this->payload([], [], [], ['po_no' => 'BOGH 89-26', 'customer_po_date' => '2026-10-09', 'notes' => 'fixed']));

        $so = $this->so->fresh();
        $this->assertSame('BOGH 89-26', $so->po_no);
        $this->assertSame('2026-10-09', $so->customer_po_date->toDateString());
        $this->assertSame('fixed', $so->notes);
    }

    public function test_a_closed_or_cancelled_order_cannot_be_edited(): void
    {
        $this->so->update(['status' => 'closed']);
        try {
            $this->service->updateIssuedOrder($this->so->fresh(), $this->payload());
            $this->fail('a closed order was edited');
        } catch (ValidationException) {
        }

        $this->so->update(['status' => 'cancelled']);
        $this->expectException(ValidationException::class);
        $this->service->updateIssuedOrder($this->so->fresh(), $this->payload());
    }

    public function test_over_http_only_a_full_admin_edits_and_it_is_logged(): void
    {
        $a = $this->lines()[0];

        $this->actingAs($this->admin)->get(route('sales-orders.edit', $this->so))
            ->assertOk()->assertSee('Save Changes')->assertSee('"locked":true', false);

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->put(route('sales-orders.update', $this->so), $this->payload(['qty' => 8]));
        $this->assertSame(10, $a->fresh()->qty);

        $this->actingAs($this->admin)->put(route('sales-orders.update', $this->so), $this->payload(['qty' => 8]))
            ->assertRedirect(route('sales-orders.show', $this->so));
        $this->assertSame(8, $a->fresh()->qty);
        $this->assertDatabaseHas('activity_logs', ['module' => 'SalesOrder', 'action' => 'edited', 'loggable_id' => $this->so->id]);
    }

    public function test_a_refused_edit_changes_nothing(): void
    {
        $a = $this->lines()[0];

        $this->actingAs($this->admin)->put(route('sales-orders.update', $this->so), $this->payload(['qty' => 3], ['qty' => 9]))
            ->assertSessionHasErrors('items');

        $this->assertSame(10, $a->fresh()->qty);
        $this->assertSame(4, $this->lines()[1]->qty, 'the valid change on line B is rolled back too');
    }
}
